<?php

namespace Tests\Unit;

use App\Services\WebPush;
use Tests\TestCase;

class WebPushTest extends TestCase
{
    /** RFC 8291 section 5 / appendix A: the worked example, byte for byte. */
    public function test_encryption_matches_the_rfc_8291_example(): void
    {
        $body = (new WebPush)->encrypt(
            'When I grow up, I want to be a watermelon',
            'BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4',
            'BTBZMqHH6r4Tts7J_aSIgg',
            WebPush::b64d('DGv6ra1nlYgDCS1FRnbzlw'),
            'yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw',
        );

        $this->assertSame(
            'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN',
            WebPush::b64e($body),
        );
    }

    /** The receiving side (as a browser does it) can decrypt what we send. */
    public function test_a_browser_can_decrypt_our_messages(): void
    {
        $ua = WebPush::newKey();
        $ec = openssl_pkey_get_details($ua)['ec'];
        $uaPublic = "\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
        $auth = random_bytes(16);

        $body = (new WebPush)->encrypt('{"title":"New order #1042"}', WebPush::b64e($uaPublic), WebPush::b64e($auth));

        // Decrypt per RFC 8291.
        $salt = substr($body, 0, 16);
        $this->assertSame(4096, unpack('N', substr($body, 16, 4))[1]);
        $asPublic = substr($body, 21, 65);
        $cipher = substr($body, 86, -16);
        $tag = substr($body, -16);
        $peer = openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode(hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').$asPublic), 64, "\n")."-----END PUBLIC KEY-----\n");
        $secret = openssl_pkey_derive($peer, $ua, 32);
        $ikm = hash_hmac('sha256', "WebPush: info\0".$uaPublic.$asPublic."\x01", hash_hmac('sha256', $secret, $auth, true), true);
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        $cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\0\x01", $prk, true), 0, 16);
        $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\0\x01", $prk, true), 0, 12);

        $this->assertSame('{"title":"New order #1042"}'."\x02", openssl_decrypt($cipher, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag));
    }

    public function test_only_real_push_services_are_accepted(): void
    {
        $this->assertTrue(WebPush::allowedEndpoint('https://fcm.googleapis.com/fcm/send/abc'));
        $this->assertTrue(WebPush::allowedEndpoint('https://web.push.apple.com/QGx'));
        $this->assertTrue(WebPush::allowedEndpoint('https://updates.push.services.mozilla.com/wpush/v2/x'));
        $this->assertTrue(WebPush::allowedEndpoint('https://wns2-db5p.notify.windows.com/w/?token=x'));

        $this->assertFalse(WebPush::allowedEndpoint('http://fcm.googleapis.com/fcm/send/abc'));
        $this->assertFalse(WebPush::allowedEndpoint('https://127.0.0.1/admin'));
        $this->assertFalse(WebPush::allowedEndpoint('https://evilgoogleapis.com/x'));
        $this->assertFalse(WebPush::allowedEndpoint('https://fcm.googleapis.com.evil.test/x'));
        $this->assertFalse(WebPush::allowedEndpoint('https://user@fcm.googleapis.com/x'));
        $this->assertFalse(WebPush::allowedEndpoint('https://fcm.googleapis.com:8443/x'));
    }
}
