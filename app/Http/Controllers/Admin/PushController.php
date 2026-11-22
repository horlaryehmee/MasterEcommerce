<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\AdminAlerts;
use App\Services\WebPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Turning phone/desktop notifications on and off, per device. */
class PushController extends Controller
{
    /**
     * A device turned notifications on (the browser's PushSubscription.toJSON()).
     * `silent`: sent automatically when the admin opens the dashboard on a device
     * that already allows notifications. It never re-enables a device that was
     * switched off from the device list, and sends no test notification.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'silent' => ['sometimes', 'boolean'],
            'endpoint' => ['required', 'string', 'max:1000', 'url:https'],
            'keys.p256dh' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{86,88}={0,2}$/'],
            'keys.auth' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{21,24}={0,2}$/'],
        ]);
        // Only the browsers' push services: never an arbitrary address for the server to call.
        if (! WebPush::allowedEndpoint($data['endpoint'])) {
            throw ValidationException::withMessages(['endpoint' => 'This browser’s notification service isn’t supported. Try Chrome, Edge, Firefox or Safari.']);
        }

        $silent = $request->boolean('silent');
        $hash = hash('sha256', $data['endpoint']);
        $existing = PushSubscription::query()->where('endpoint_hash', $hash)->first();
        if ($silent && $existing?->disabled_at) {
            return response()->json(['ok' => false, 'disabled' => true]);
        }

        $sub = PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => $hash],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $data['endpoint'],
                'public_key' => rtrim($data['keys']['p256dh'], '='),
                'auth_token' => rtrim($data['keys']['auth'], '='),
                'device' => $this->deviceName((string) $request->userAgent()),
                'disabled_at' => null,
            ],
        );

        if ($silent) {
            return response()->json(['ok' => true]);
        }

        $sent = app(AdminAlerts::class)->test($sub);

        return back()->with($sent ? 'success' : 'error', $sent
            ? 'Notifications are on for this device. A test notification is on its way.'
            : 'Notifications were turned on, but the test didn’t arrive. Check this device’s notification settings.');
    }

    /**
     * Stop notifications: from the device list (by id: switched off, so that
     * device won't quietly turn itself back on), or on this device (by endpoint).
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['id' => ['nullable', 'integer'], 'endpoint' => ['nullable', 'string', 'max:1000']]);
        $mine = PushSubscription::query()->where('user_id', $request->user()->id);

        if ($request->input('id')) {
            $mine->whereKey($request->input('id'))->update(['disabled_at' => now()]);
        } else {
            $mine->where('endpoint_hash', hash('sha256', (string) $request->input('endpoint')))->delete();
        }

        return back()->with('success', 'Notifications turned off for that device.');
    }

    public function test(Request $request): RedirectResponse
    {
        $subs = PushSubscription::query()->active()->where('user_id', $request->user()->id)->get();
        $sent = $subs->filter(fn ($s) => app(AdminAlerts::class)->test($s))->count();

        return back()->with($sent ? 'success' : 'error', $sent
            ? "Test notification sent to {$sent} ".($sent === 1 ? 'device' : 'devices').'.'
            : 'No device received it. Turn notifications on again on your phone.');
    }

    /** "Chrome on Android", "Safari on iPhone", … */
    protected function deviceName(string $ua): string
    {
        $os = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS') => 'Mac',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'device',
        };
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'Firefox') => 'Firefox',
            str_contains($ua, 'Chrome') => 'Chrome',
            str_contains($ua, 'Safari') => 'Safari',
            default => 'Browser',
        };

        return "{$browser} on {$os}";
    }
}
