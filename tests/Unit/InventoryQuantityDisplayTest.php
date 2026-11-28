<?php

namespace Tests\Unit;

use App\Support\InventoryQuantityDisplay;
use PHPUnit\Framework\TestCase;

class InventoryQuantityDisplayTest extends TestCase
{
    public function test_quantities_are_readable_without_changing_precision(): void
    {
        foreach ([
            ['19.000','piece','19','قطعة'],
            ['0.350','kg','350','غرام'],
            ['0.970','kg','970','غرام'],
            ['2.850','kg','2.85','كغم'],
            ['1.235','kg','1.235','كغم'],
            ['0.001','kg','1','غرام'],
            ['0.500','l','500','مل'],
            ['0.125','piece','0.125','قطعة'],
            ['0.000','kg','0','كغم'],
        ] as [$quantity,$code,$amount,$unit]) {
            $result = InventoryQuantityDisplay::format($quantity,$code);
            $this->assertSame($amount,$result['amount']);
            $this->assertSame($unit,$result['unit']);
        }
        $this->assertSame('قطعة',InventoryQuantityDisplay::format('2',null,'piece')['unit']);
        $this->assertSame('كرتونة',InventoryQuantityDisplay::format('2','carton',null,'كرتونة')['unit']);
        $this->assertFalse(InventoryQuantityDisplay::format('2')['known']);
        $this->assertSame('كغم',InventoryQuantityDisplay::format('0.350','kg')['base_unit']);
    }
}
