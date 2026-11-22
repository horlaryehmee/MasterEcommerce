<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\PushSubscription;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Web Push: notifications on the admin's phone or computer, even when the app
 * is closed. Built on PHP's OpenSSL only (no extra libraries or services):
 *
 *  - Messages are end-to-end encrypted for each device (RFC 8291, aes128gcm);
 *    the push service (Google, Apple, Mozilla, Microsoft) can't read them.
 *  - Requests are signed with this site's VAPID key (RFC 8292), so only this
 *    server can send to its subscribers.
 *  - Messages only go to the push services browsers use (no other addresses).
 */
class WebPush
{
    /** Hosts of the browsers' push services. Subscriptions elsewhere are refused. */
    public const PUSH_HOSTS = ['fcm.googleapis.com', 'android.googleapis.com', 'push.services.mozilla.com', 'push.apple.com', 'notify.windows.com'];

    /** DER prefix that turns a raw P-256 public point into a SubjectPublicKeyInfo. */
    protected const P256_SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    public static function allowedEndpoint(string $url): bool
    {
        $p = parse_url($url);
        if (($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['port'])) {
            return false;
        }
        $host = strtolower($p['host']);
        foreach (self::PUSH_HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }

    /** This site's VAPID public key (base64url), made on first use. */
    public function publicKey(): string
    {
        return $this->keys()['public'];
    }

    /**
     * Sends a notification to every device of the given admins (all admins if null).
     * Payload: title, body, url, tag, badge (number for the app icon).
     */
    public function notify(array $payload, ?array $userIds = null): int
    {
        $subs = PushSubscription::query()->active()->when($userIds, fn ($q) => $q->whereIn('user_id', $userIds))->get();

        return $this->send($subs->all(), $payload);
    }

    /** @param  array<int, PushSubscription>  $subscriptions */
    public function send(array $subscriptions, array $payload): int
    {
        if (! $subscriptions) {
            return 0;
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $requests = [];
        foreach ($subscriptions as $sub) {
            try {
                $requests[$sub->id] = [$sub, $this->encrypt($json, $sub->public_key, $sub->auth_token), $this->vapidHeader($sub->endpoint)];
            } catch (Throwable $e) {
                Log::notice('Push message could not be prepared', ['subscription' => $sub->id, 'error' => $e->getMessage()]);
            }
        }

        $responses = Http::pool(fn (Pool $pool) => array_map(fn ($r) => $pool->as((string) $r[0]->id)
            ->withHeaders([
                'Authorization' => $r[2],
                'Content-Encoding' => 'aes128gcm',
                'Content-Type' => 'application/octet-stream',
                'TTL' => '86400',
                'Urgency' => 'high',
            ])
            ->timeout(10)
            ->withBody($r[1], 'application/octet-stream')
            ->post($r[0]->endpoint), $requests));

        $sent = 0;
        foreach ($requests as $id => [$sub]) {
            $response = $responses[(string) $id] ?? null;
            if ($response instanceof \Illuminate\Http\Client\Response && $response->successful()) {
                $sent++;
                $sub->forceFill(['last_used_at' => now()])->saveQuietly();
            } elseif ($response instanceof \Illuminate\Http\Client\Response && in_array($response->status(), [404, 410], true)) {
                // The device unsubscribed or the browser data was cleared.
                $sub->delete();
            } else {
                Log::notice('Push message not delivered', ['subscription' => $id, 'status' => $response instanceof \Illuminate\Http\Client\Response ? $response->status() : 'no response']);
            }
        }

        return $sent;
    }

    /**
     * RFC 8291 message encryption. $salt and $senderPrivateKey are only passed
     * in tests (to check against the RFC's example); normally both are random.
     */
    public function encrypt(string $plaintext, string $userPublicKey, string $authSecret, ?string $salt = null, ?string $senderPrivateKey = null): string
    {
        $uaPublic = self::b64d($userPublicKey);
        $auth = self::b64d($authSecret);
        if (strlen($uaPublic) !== 65 || $uaPublic[0] !== "\x04" || strlen($auth) !== 16) {
            throw new RuntimeException('Invalid subscription keys.');
        }
        if (strlen($plaintext) > 3800) {
            throw new RuntimeException('Notification too long.');
        }

        // Sender's one-time key pair.
        if ($senderPrivateKey !== null) {
            $d = self::b64d($senderPrivateKey);
            $asPrivate = openssl_pkey_get_private(self::ecPrivatePem($d, self::publicPointFromPrivate($d)));
        } else {
            $asPrivate = self::newKey();
        }
        $details = openssl_pkey_get_details($asPrivate);
        $asPublic = "\x04".str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT).str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);

        $secret = openssl_pkey_derive(openssl_pkey_get_public(self::publicPem($uaPublic)), $asPrivate, 32);
        if ($secret === false) {
            throw new RuntimeException('Key agreement failed.');
        }

        $salt ??= random_bytes(16);
        $salt = strlen($salt) === 16 ? $salt : self::b64d($salt);

        $prkKey = hash_hmac('sha256', $secret, $auth, true);
        $ikm = hash_hmac('sha256', "WebPush: info\0".$uaPublic.$asPublic."\x01", $prkKey, true);
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        $cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\0\x01", $prk, true), 0, 16);
        $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\0\x01", $prk, true), 0, 12);

        // One record: the message, then the 0x02 "last record" delimiter.
        $tag = '';
        $cipher = openssl_encrypt($plaintext."\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return $salt.pack('N', 4096).chr(65).$asPublic.$cipher.$tag;
    }

    /** "vapid t=<signed JWT>, k=<public key>" for the endpoint's push service. */
    public function vapidHeader(string $endpoint): string
    {
        $keys = $this->keys();
        $p = parse_url($endpoint);
        $claims = [
            'aud' => $p['scheme'].'://'.$p['host'],
            'exp' => time() + 12 * 3600,
            'sub' => $this->contact(),
        ];
        $unsigned = self::b64e(json_encode(['typ' => 'JWT', 'alg' => 'ES256'])).'.'.self::b64e(json_encode($claims, JSON_UNESCAPED_SLASHES));

        if (! openssl_sign($unsigned, $der, openssl_pkey_get_private($keys['private_pem']), OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign the push request.');
        }

        return 'vapid t='.$unsigned.'.'.self::b64e(self::derToRaw($der)).', k='.$keys['public'];
    }

    /** Contact the push services can use if something goes wrong: the site address. */
    protected function contact(): string
    {
        $url = rtrim((string) config('app.url'), '/');

        return str_starts_with($url, 'https://') ? $url : 'mailto:'.(BusinessSetting::current()->email ?: 'admin@example.com');
    }

    /** @return array{private_pem: string, public: string} */
    protected function keys(): array
    {
        $s = BusinessSetting::current();
        if (blank($s->vapid_private_key) || blank($s->vapid_public_key)) {
            $key = self::newKey();
            openssl_pkey_export($key, $pem) || openssl_pkey_export($key, $pem, null, ['config' => resource_path('openssl/openssl.cnf')]);
            $ec = openssl_pkey_get_details($key)['ec'];
            $public = self::b64e("\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT));
            $s->update(['vapid_private_key' => $pem, 'vapid_public_key' => $public]);
        }

        return ['private_pem' => $s->vapid_private_key, 'public' => $s->vapid_public_key];
    }

    /** A new P-256 key. Hosts without a system openssl.cnf (Windows) use the app's minimal one. */
    public static function newKey(): \OpenSSLAsymmetricKey
    {
        $options = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
        $key = openssl_pkey_new($options) ?: openssl_pkey_new($options + ['config' => resource_path('openssl/openssl.cnf')]);
        while (openssl_error_string() !== false) {
            // Clear OpenSSL's error queue so later calls report their own errors.
        }

        return $key ?: throw new RuntimeException('This server can’t create encryption keys (OpenSSL).');
    }

    protected static function publicPem(string $point): string
    {
        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode(hex2bin(self::P256_SPKI_PREFIX).$point), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    protected static function ecPrivatePem(string $d, string $point): string
    {
        $der = hex2bin('30770201010420').$d.hex2bin('a00a06082a8648ce3d030107a144034200').$point;

        return "-----BEGIN EC PRIVATE KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END EC PRIVATE KEY-----\n";
    }

    /** Public point for a raw private key (tests only): OpenSSL fills it in from a key without one. */
    protected static function publicPointFromPrivate(string $d): string
    {
        $der = hex2bin('30310201010420').$d.hex2bin('a00a06082a8648ce3d030107');
        $key = openssl_pkey_get_private("-----BEGIN EC PRIVATE KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END EC PRIVATE KEY-----\n");
        if (! $key) {
            throw new RuntimeException('Invalid private key.');
        }
        $ec = openssl_pkey_get_details($key)['ec'];

        return "\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
    }

    /** DER ECDSA signature → 64-byte r||s (what JWT ES256 expects). */
    protected static function derToRaw(string $der): string
    {
        $pos = 2 + (ord($der[1]) & 0x80 ? ord($der[1]) & 0x7F : 0);
        $out = '';
        for ($i = 0; $i < 2; $i++) {
            $len = ord($der[$pos + 1]);
            $int = substr($der, $pos + 2, $len);
            $out .= str_pad(ltrim($int, "\0"), 32, "\0", STR_PAD_LEFT);
            $pos += 2 + $len;
        }

        return $out;
    }

    public static function b64e(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function b64d(string $text): string
    {
        return (string) base64_decode(strtr($text, '-_', '+/').str_repeat('=', (4 - strlen($text) % 4) % 4), true);
    }
}
