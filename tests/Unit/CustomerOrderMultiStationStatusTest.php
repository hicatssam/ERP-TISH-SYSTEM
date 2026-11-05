<?php

namespace Tests\Unit;

use App\Services\Restaurant\CustomerOrderStatusService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CustomerOrderMultiStationStatusTest extends TestCase
{
    #[DataProvider('statusCases')]
    public function test_customer_status_aggregates_every_kitchen_station(
        array $tickets,
        bool $allItemsRouted,
        string $expected
    ): void {
        $this->assertSame(
            $expected,
            CustomerOrderStatusService::aggregateKitchenStatuses(
                collect($tickets),
                $allItemsRouted
            )
        );
    }

    public static function statusCases(): array
    {
        return [
            'grill queued fries queued bar queued' => [['queued', 'queued', 'queued'], true, 'accepted'],
            'grill preparing fries queued bar queued' => [['preparing', 'queued', 'queued'], true, 'preparing'],
            'grill ready fries preparing bar ready' => [['ready', 'preparing', 'ready'], true, 'preparing'],
            'grill ready fries queued bar served' => [['ready', 'queued', 'served'], true, 'preparing'],
            'grill ready fries ready bar ready' => [['ready', 'ready', 'ready'], true, 'ready'],
            'grill served fries ready bar ready' => [['served', 'ready', 'ready'], true, 'ready'],
            'grill served fries served bar served' => [['served', 'served', 'served'], true, 'completed'],
            'grill ready only while another line has no ticket' => [['ready'], false, 'preparing'],
            'grill served only while another line has no ticket' => [['served'], false, 'preparing'],
            'cancelled station and other two ready' => [['cancelled', 'ready', 'ready'], true, 'ready'],
            'all stations cancelled without cancelling order' => [['cancelled', 'cancelled'], true, 'accepted'],
            'all stations empty' => [[], true, 'accepted'],
        ];
    }
}
