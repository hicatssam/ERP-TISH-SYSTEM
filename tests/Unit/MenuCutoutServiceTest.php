<?php

namespace Tests\Unit;

use App\Services\Restaurant\MenuCutoutService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MenuCutoutServiceTest extends TestCase
{
    public function test_remote_images_are_not_downloaded_or_processed(): void
    {
        config(['menu_cutouts.enabled' => true]);
        $cutouts = app(MenuCutoutService::class);

        $this->assertNull($cutouts->sourceFile('https://example.com/product.png'));
        $this->assertNull($cutouts->cutoutUrl('https://example.com/product.png'));
        $this->assertFalse($cutouts->generate('https://example.com/product.png'));
    }

    public function test_original_image_is_used_when_no_cutout_exists(): void
    {
        config(['menu_cutouts.enabled' => true]);
        Storage::fake('public');

        // Valid minimal PNG; test does not require Python or download any model.
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jR9kAAAAASUVORK5CYII=',
            true
        );
        Storage::disk('public')->put('products/demo.png', $png);

        $cutouts = app(MenuCutoutService::class);
        $this->assertNotNull($cutouts->sourceFile('products/demo.png'));
        $this->assertNull($cutouts->cutoutUrl('products/demo.png'));
        $this->assertTrue($cutouts->needsGeneration('products/demo.png'));
        $this->assertSame(
            'menu-cutouts/'.sha1('products/demo.png').'.png',
            $cutouts->outputPath('products/demo.png')
        );
    }

    public function test_feature_can_be_disabled_without_removing_originals(): void
    {
        config(['menu_cutouts.enabled' => false]);
        $cutouts = app(MenuCutoutService::class);

        $this->assertNull($cutouts->cutoutUrl('products/demo.png'));
        $this->assertFalse($cutouts->needsGeneration('products/demo.png'));
    }
}
