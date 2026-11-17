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
        config([
            'services.tish_push.public_key' => 'a-public-key',
            'services.tish_push.private_key' => 'a-private-key',
            'services.tish_push.subject' => 'mailto:test@example.com',
        ]);

        $response = $this->getJson(route('customer-menu.push.public-key'))
            ->assertOk()
            ->assertJson(['enabled' => true, 'public_key' => 'a-public-key']);

        $this->assertStringNotContainsString('a-private-key', $response->getContent());
    }
}
