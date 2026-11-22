<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentReceipt;
use App\Services\AdminAlerts;
use App\Services\ImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * A customer uploads their bank-transfer receipt from the order page (private
 * order link). Images are re-encoded to WebP (which also strips anything hidden
 * in them); PDFs are checked to really be PDFs without scripts. Files are stored
 * privately and only an admin can open them.
 */
class ReceiptController extends Controller
{
    public function store(Request $request, string $token, ImageStorage $images): RedirectResponse
    {
        $order = Order::query()->where('token', $token)->firstOrFail();
        abort_unless($order->acceptsReceipts(), 403, 'This order doesn’t need a payment receipt.');

        $request->validate([
            'receipt' => ['required', 'file', 'max:'.PaymentReceipt::MAX_KB, 'mimes:jpg,jpeg,png,webp,pdf', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf'],
        ], [
            'receipt.required' => 'Choose a photo, screenshot or PDF of your receipt.',
            'receipt.max' => 'That file is too big. The limit is 8 MB.',
            'receipt.mimes' => 'Upload a photo or screenshot (JPG, PNG, WebP) or a PDF.',
            'receipt.mimetypes' => 'Upload a photo or screenshot (JPG, PNG, WebP) or a PDF.',
        ]);

        $file = $request->file('receipt');
        $isPdf = str_starts_with((string) file_get_contents($file->getRealPath(), false, null, 0, 5), '%PDF-');

        try {
            $path = $isPdf ? $this->storePdf($file) : $images->store($file, 'receipts', 'webp', PaymentReceipt::DISK);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['receipt' => $e->getMessage()]);
        }

        try {
            $receipt = DB::transaction(function () use ($order, $path, $isPdf) {
                $order = Order::query()->lockForUpdate()->findOrFail($order->id);
                abort_unless($order->acceptsReceipts(), 403);
                if ($order->receipts()->count() >= PaymentReceipt::MAX_PER_ORDER) {
                    throw ValidationException::withMessages(['receipt' => 'You’ve uploaded the maximum number of receipts for this order. Please contact us if you need help.']);
                }

                return $order->receipts()->create([
                    'path' => $path,
                    'kind' => $isPdf ? 'pdf' : 'image',
                    'size' => (int) Storage::disk(PaymentReceipt::DISK)->size($path),
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk(PaymentReceipt::DISK)->delete($path);
            throw $e;
        }
        app(AdminAlerts::class)->receiptUploaded($receipt);

        return back()->with('receipt_uploaded', true);
    }

    /** "I've made the payment": move on to the thank-you page; the shop verifies the transfer. */
    public function reported(string $token): RedirectResponse
    {
        $order = Order::query()->where('token', $token)->firstOrFail();
        abort_unless($order->payment_method === 'bank_transfer' && $order->acceptsReceipts(), 403, 'This order doesn’t need a payment confirmation.');

        if (! $order->payment_reported_at) {
            $order->update(['payment_reported_at' => now()]);
            app(AdminAlerts::class)->paymentReported($order);
        }

        return redirect()->route('orders.show', $order->token)->with('payment_reported', true);
    }

    /** A real PDF, with no scripts, launch actions or attached files. */
    protected function storePdf(UploadedFile $file): string
    {
        $bytes = (string) file_get_contents($file->getRealPath());
        if (! str_starts_with($bytes, '%PDF-') || ! str_contains(substr($bytes, -2048), '%%EOF')) {
            throw new RuntimeException('That PDF couldn’t be read. Try a screenshot of the receipt instead.');
        }
        if (preg_match('#/(JavaScript|JS|Launch|EmbeddedFile|RichMedia|XFA|SubmitForm|ImportData)\b#', $bytes)) {
            throw new RuntimeException('That PDF can’t be accepted. Please upload a screenshot or photo of the receipt instead.');
        }

        $path = 'receipts/'.Str::lower((string) Str::ulid()).'.pdf';
        if (! Storage::disk(PaymentReceipt::DISK)->put($path, $bytes)) {
            throw new RuntimeException('Could not save the receipt.');
        }

        return $path;
    }
}
