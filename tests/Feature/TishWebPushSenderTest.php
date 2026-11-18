<?php

namespace Tests\Feature;

use App\Services\Restaurant\TishWebPushSender;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TishWebPushSenderTest extends TestCase
{
    private function url64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private function decode64(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4));
    }

    private function hkdf(string $ikm, string $salt, string $info, int $length): string
    {
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        $block = '';
        $output = '';
        for ($i = 1; strlen($output) < $length; $i++) {
            $block = hash_hmac('sha256', $block . $info . chr($i), $prk, true);
            $output .= $block;
        }
        return substr($output, 0, $length);
    }

    public function test_endpoint_allowlist_rejects_ssrf_targets(): void
    {
        $this->assertTrue(TishWebPushSender::allowedEndpoint('https://fcm.googleapis.com/fcm/send/test'));
        $this->assertTrue(TishWebPushSender::allowedEndpoint('https://updates.push.services.mozilla.com/wpush/v2/test'));
        $this->assertTrue(TishWebPushSender::allowedEndpoint('https://web.push.apple.com/example'));
        $this->assertFalse(TishWebPushSender::allowedEndpoint('http://fcm.googleapis.com/fcm/send/test'));
        $this->assertFalse(TishWebPushSender::allowedEndpoint('https://fcm.googleapis.com.evil.example/'));
        $this->assertFalse(TishWebPushSender::allowedEndpoint('https://127.0.0.1/'));
        $this->assertFalse(TishWebPushSender::allowedEndpoint('https://fcm.googleapis.com:444/'));
    }

    public function test_encrypted_message_can_be_decrypted_by_browser_key(): void
    {
        $vapidKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $vapid = openssl_pkey_get_details($vapidKey)['ec'];
        config([
            'services.tish_push.public_key' => $this->url64("\x04" . $vapid['x'] . $vapid['y']),
            'services.tish_push.private_key' => $this->url64($vapid['d']),
            'services.tish_push.subject' => 'mailto:test@example.com',
        ]);

        $devicePrivate = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $device = openssl_pkey_get_details($devicePrivate)['ec'];
        $clientPublic = "\x04" . $device['x'] . $device['y'];
        $auth = random_bytes(16);
        $requestBody = null;
        $requestAuth = null;

        Http::fake(function ($request) use (&$requestBody, &$requestAuth) {
            $requestBody = $request->body();
            $requestAuth = $request->header('Authorization')[0] ?? null;
            return Http::response('', 201);
        });

        $message = [
            'title' => 'TISH',
            'body' => 'Your burger is preparing',
            'url' => 'https://example.com/menu/track/abcdef',
            'state' => 'preparing',
        ];
        $sender = app(TishWebPushSender::class);
        $this->assertTrue($sender->available());
        $this->assertSame(201, $sender->deliver(
            'https://fcm.googleapis.com/fcm/send/test',
            $this->url64($clientPublic), $this->url64($auth), $message
        ));
        $this->assertNotNull($requestAuth);
        $this->assertStringStartsWith('vapid t=', $requestAuth);
        $this->assertIsString($requestBody);
        $this->assertGreaterThan(100, strlen($requestBody));
        $this->assertSame(4096, unpack('N', substr($requestBody, 16, 4))[1]);
        $this->assertSame(65, ord($requestBody[20]));

        $salt = substr($requestBody, 0, 16);
        $senderPublicBytes = substr($requestBody, 21, 65);
        $senderPublicDer = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $senderPublicBytes;
        $senderPublicPem = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($senderPublicDer), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
        $shared = openssl_pkey_derive(openssl_pkey_get_public($senderPublicPem), $devicePrivate);
        $ikm = $this->hkdf($shared, $auth, "WebPush: info\x00" . $clientPublic . $senderPublicBytes, 32);
        $cek = $this->hkdf($ikm, $salt, "Content-Encoding: aes128gcm\x00", 16);
        $nonce = $this->hkdf($ikm, $salt, "Content-Encoding: nonce\x00", 12);
        $cipher = substr($requestBody, 86, -16);
        $tag = substr($requestBody, -16);
        $plain = openssl_decrypt($cipher, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        $this->assertIsString($plain);
        $this->assertSame("\x02", substr($plain, -1));
        $this->assertSame($message, json_decode(substr($plain, 0, -1), true));
    }
}
