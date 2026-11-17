<?php

namespace App\Services\Restaurant;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class TishWebPushSender
{
    private static function decode(string $value): string
    {
        $result = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        if ($result === false) {
            throw new RuntimeException('Invalid base64url');
        }
        return $result;
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function hkdf(string $ikm, string $salt, string $info, int $length): string
    {
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        $output = '';
        $block = '';
        for ($i = 1; strlen($output) < $length; $i++) {
            $block = hash_hmac('sha256', $block . $info . chr($i), $prk, true);
            $output .= $block;
        }
        return substr($output, 0, $length);
    }

    public function available(): bool
    {
        return extension_loaded('openssl')
            && filled(config('services.tish_push.public_key'))
            && filled(config('services.tish_push.private_key'))
            && filled(config('services.tish_push.subject'));
    }

    public static function allowedEndpoint(string $endpoint): bool
    {
        $host = strtolower((string) parse_url($endpoint, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($endpoint, PHP_URL_SCHEME));
        $port = parse_url($endpoint, PHP_URL_PORT);
        if ($scheme !== 'https' || $port !== null || $host === '') return false;
        foreach (['fcm.googleapis.com', 'updates.push.services.mozilla.com', 'push.services.mozilla.com', 'web.push.apple.com', 'push.apple.com'] as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) return true;
        }
        return false;
    }

    private function vapidPrivateKey(): mixed
    {
        $raw = self::decode((string) config('services.tish_push.private_key'));
        if (strlen($raw) !== 32) throw new RuntimeException('VAPID key must be P-256');
        // ASN.1 SEC1 ECPrivateKey with named curve prime256v1.
        $der = hex2bin('30310201010420') . $raw . hex2bin('a00a06082a8648ce3d030107');
        $pem = "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
        $key = openssl_pkey_get_private($pem);
        if (! $key) throw new RuntimeException('Invalid VAPID private key');
        return $key;
    }

    private static function publicFromRaw(string $raw): mixed
    {
        if (strlen($raw) !== 65 || ord($raw[0]) !== 4) throw new RuntimeException('Invalid P-256 public key');
        // SubjectPublicKeyInfo: id-ecPublicKey / prime256v1 / uncompressed point.
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $raw;
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
        $key = openssl_pkey_get_public($pem);
        if (! $key) throw new RuntimeException('Invalid subscription public key');
        return $key;
    }

    private static function signatureToJose(string $der): string
    {
        // Convert OpenSSL's ASN.1 DER ECDSA signature to the 64-byte JOSE format.
        if (ord($der[0]) !== 0x30) throw new RuntimeException('Invalid ES256 signature');
        $i = 1;
        $sequenceLength = ord($der[$i++]);
        if ($sequenceLength & 0x80) $i += $sequenceLength & 0x7f;
        $values = [];
        for ($n = 0; $n < 2; $n++) {
            if (ord($der[$i++]) !== 0x02) throw new RuntimeException('Invalid DER integer');
            $len = ord($der[$i++]);
            $value = ltrim(substr($der, $i, $len), "\x00");
            $i += $len;
            if (strlen($value) > 32) throw new RuntimeException('Invalid ES256 width');
            $values[] = str_pad($value, 32, "\x00", STR_PAD_LEFT);
        }
        return implode('', $values);
    }

    public function deliver(string $endpoint, string $p256dh, string $authSecret, array $message): int
    {
        if (! $this->available() || ! self::allowedEndpoint($endpoint)) {
            throw new RuntimeException('Web Push not configured or endpoint disallowed');
        }
        $vapidPublic = self::decode((string) config('services.tish_push.public_key'));
        if (strlen($vapidPublic) !== 65 || $vapidPublic[0] !== "\x04") {
            throw new RuntimeException('Invalid VAPID public key');
        }
        $client = self::decode($p256dh);
        $auth = self::decode($authSecret);
        if (strlen($auth) < 16) throw new RuntimeException('Invalid subscription secret');
        $clientKey = self::publicFromRaw($client);
        $serverKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        if (! $serverKey) throw new RuntimeException('Cannot create ephemeral ECDH key');
        $details = openssl_pkey_get_details($serverKey);
        $serverPublic = "\x04" . $details['ec']['x'] . $details['ec']['y'];
        $shared = openssl_pkey_derive($clientKey, $serverKey);
        if (! is_string($shared)) throw new RuntimeException('ECDH key agreement failed');

        // RFC 8291 Web Push encryption, aes128gcm one record.
        $ikm = self::hkdf($shared, $auth, "WebPush: info\x00" . $client . $serverPublic, 32);
        $salt = random_bytes(16);
        $cek = self::hkdf($ikm, $salt, "Content-Encoding: aes128gcm\x00", 16);
        $nonce = self::hkdf($ikm, $salt, "Content-Encoding: nonce\x00", 12);
        $plain = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\x02";
        if (strlen($plain) + 16 >= 4096) {
            throw new RuntimeException('Web Push message exceeds one record');
        }
        $tag = '';
        $encrypted = openssl_encrypt($plain, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($encrypted === false) throw new RuntimeException('AES-GCM encryption failed');
        $body = $salt . pack('N', 4096) . chr(65) . $serverPublic . $encrypted . $tag;

        $origin = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST);
        $header = self::encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_THROW_ON_ERROR));
        $claims = self::encode(json_encode([
            'aud' => $origin,
            'exp' => time() + 3600,
            'sub' => (string) config('services.tish_push.subject'),
        ], JSON_THROW_ON_ERROR));
        $signing = $header . '.' . $claims;
        if (! openssl_sign($signing, $signature, $this->vapidPrivateKey(), OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('VAPID signing failed');
        }
        $jwt = $signing . '.' . self::encode(self::signatureToJose($signature));
        $response = Http::withHeaders([
            'TTL' => '3600',
            'Content-Encoding' => 'aes128gcm',
            'Authorization' => 'vapid t=' . $jwt . ', k=' . (string) config('services.tish_push.public_key'),
            'Urgency' => 'normal',
        ])->timeout(10)->withBody($body, 'application/octet-stream')->post($endpoint);
        return $response->status();
    }
}
