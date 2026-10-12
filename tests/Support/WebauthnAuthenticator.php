<?php

namespace Tests\Support;

/** Test authenticator: real ES256 keys, CBOR registration and signed assertions. */
trait WebauthnAuthenticator
{
    private function authenticator(): array
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $details = openssl_pkey_get_details($key);

        return ['key' => $key, 'public_key' => $details['key'], 'x' => $details['ec']['x'],
            'y' => $details['ec']['y'], 'id' => random_bytes(32)];
    }

    private function b64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function cborBytes(string $value): string
    {
        return strlen($value) < 256 ? "\x58".chr(strlen($value)).$value : "\x59".pack('n', strlen($value)).$value;
    }

    private function registration(array $authenticator, array $options, string $origin = 'https://attendance.test', int $flags = 0x45): array
    {
        $cose = "\xa5\x01\x02\x03\x26\x20\x01\x21\x58\x20".$authenticator['x']."\x22\x58\x20".$authenticator['y'];
        $authData = hash('sha256', $options['publicKey']['rp']['id'], true).chr($flags).pack('N', 0)
            .str_repeat("\0", 16).pack('n', strlen($authenticator['id'])).$authenticator['id'].$cose;
        $attestation = "\xa3\x63fmt\x64none\x67attStmt\xa0\x68authData".$this->cborBytes($authData);
        $clientData = json_encode(['type' => 'webauthn.create', 'challenge' => $options['publicKey']['challenge'],
            'origin' => $origin, 'crossOrigin' => false], JSON_THROW_ON_ERROR);

        return ['token' => $options['token'] ?? 'unused', 'type' => 'public-key', 'id' => $this->b64($authenticator['id']),
            'response' => ['clientDataJSON' => $this->b64($clientData), 'attestationObject' => $this->b64($attestation)]];
    }

    private function assertion(array $authenticator, array $options, int $counter = 1, int $flags = 0x05,
        string $origin = 'https://attendance.test', ?string $handle = null): array
    {
        $authData = hash('sha256', $options['publicKey']['rpId'], true).chr($flags).pack('N', $counter);
        $clientData = json_encode(['type' => 'webauthn.get', 'challenge' => $options['publicKey']['challenge'],
            'origin' => $origin, 'crossOrigin' => false], JSON_THROW_ON_ERROR);
        openssl_sign($authData.hash('sha256', $clientData, true), $signature, $authenticator['key'], OPENSSL_ALGO_SHA256);

        return ['token' => $options['token'] ?? 'unused', 'type' => 'public-key', 'id' => $this->b64($authenticator['id']),
            'response' => ['clientDataJSON' => $this->b64($clientData), 'authenticatorData' => $this->b64($authData),
                'signature' => $this->b64($signature), 'userHandle' => $handle]];
    }
}
