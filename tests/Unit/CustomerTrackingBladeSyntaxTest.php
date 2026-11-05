<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class CustomerTrackingBladeSyntaxTest extends TestCase
{
    public function test_tracking_template_compiles_to_valid_php(): void
    {
        $blade = file_get_contents(resource_path('views/customer-menu/track.blade.php'));

        $this->assertIsString($blade);

        $compiled = Blade::compileString($blade);

        // The original nested @json([... map(fn ...)]) silently generated
        // invalid PHP. Parse the compiled output without executing the view.
        $tokens = token_get_all($compiled, TOKEN_PARSE);

        $this->assertNotEmpty($tokens);
    }
}
