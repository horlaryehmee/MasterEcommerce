<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentReceipt;
use App\Services\OrderMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Checking customers' bank-transfer receipts. */
class ReceiptController extends Controller
{
    /** The private file, for signed-in admins only. Never cached, never sniffed as anything else. */
    public function file(PaymentReceipt $receipt): StreamedResponse
    {
        $disk = Storage::disk(PaymentReceipt::DISK);
        abort_unless($disk->exists($receipt->path), 404);

        $pdf = $receipt->kind === 'pdf';

        return $disk->response($receipt->path, "receipt-order-{$receipt->order->number}-{$receipt->id}.".($pdf ? 'pdf' : 'webp'), [
            'Content-Type' => $pdf ? 'application/pdf' : 'image/webp',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'; frame-ancestors 'none'",
        ], $pdf ? 'attachment' : 'inline');
    }

    /** Confirm (marks the order paid and emails the customer) or reject with a reason the customer sees. */
    public function update(Request $request, PaymentReceipt $receipt, OrderMailer $mailer): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'note' => ['nullable', 'required_if:action,reject', 'string', 'max:200'],
        ], ['note.required_if' => 'Tell the customer why, e.g. “No payment received yet” or “Amount doesn’t match”.']);

        $order = $receipt->order;

        if ($data['action'] === 'reject') {
            $receipt->update(['status' => 'rejected', 'note' => trim($data['note']), 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            // Back to the payment step: the customer sees the reason there and can upload a new receipt.
            if (! $order->receipts()->where('status', 'pending')->exists()) {
                $order->update(['payment_reported_at' => null]);
            }

            return back()->with('success', 'Receipt rejected. The customer’s order link opens the payment page again, with your reason, so they can upload a new receipt.');
        }

        DB::transaction(function () use ($receipt, $order, $request) {
            $receipt->update(['status' => 'approved', 'note' => null, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            // Any other receipts still waiting on this order are settled by this one.
            $order->receipts()->where('status', 'pending')->whereKeyNot($receipt->id)->update(['status' => 'rejected', 'note' => 'Payment already confirmed.', 'reviewed_at' => now()]);

            if ($order->payment_status !== 'paid') {
                $order->payment_status = 'paid';
                $order->paid_at ??= now();
                if ($order->status === 'awaiting_payment') {
                    $order->status = 'pending';
                }
                $order->save();
            }
        });
        $emailed = $mailer->updated($order);

        return back()->with('success', "Payment confirmed: order #{$order->number} is marked as paid.".($emailed ? ' The customer has been emailed.' : ''));
    }
}
