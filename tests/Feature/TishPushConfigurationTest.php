<?php

namespace Tests\Feature;

use Tests\TestCase;

class TishPushConfigurationTest extends TestCase
{
    public function test_push_public_key_endpoint_disables_feature_without_server_keys(): void
    {
        config([
            'services.tish_push.public_key' => null,
            'services.tish_push.private_key' => null,
            'services.tish_push.subject' => null,
        ]);

        $this->getJson(route('customer-menu.push.public-key'))
            ->assertOk()
            ->assertJson(['enabled' => false, 'public_key' => null]);
    }

    public function test_push_public_key_endpoint_only_returns_public_key(): void
    {
        $pair = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $parts = openssl_pkey_get_details($pair)['ec'];
        $url64 = fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $public = $url64("\x04" . $parts['x'] . $parts['y']);
        $private = $url64($parts['d']);
        config([
            'services.tish_push.public_key' => $public,
            'services.tish_push.private_key' => $private,
            'services.tish_push.subject' => 'mailto:test@example.com',
        ]);

        $response = $this->getJson(route('customer-menu.push.public-key'))
            ->assertOk()
            ->assertJson(['enabled' => true, 'public_key' => $public]);

        $this->assertStringNotContainsString($private, $response->getContent());
    }
}
