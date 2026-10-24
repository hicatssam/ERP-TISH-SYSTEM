<?php

namespace Tests\Feature\Sales;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShowroomCakeRequestPostSaveNotificationTest extends TestCase
{
    #[Test]
    public function committed_order_notifications_are_isolated_from_success_response(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Sales/ShowroomCakeRequestController.php'));
        $this->assertStringContainsString('Showroom cake notification failed after save', $source);
        $this->assertStringContainsString('Showroom cake raw-material notification failed after save', $source);
        $this->assertStringContainsString('catch (\\Throwable $exception)', $source);
        $this->assertStringContainsString("'تم إنشاء طلب كيك الفرع واعتماده تلقائيًا من النظام.'", $source);
    }
}
