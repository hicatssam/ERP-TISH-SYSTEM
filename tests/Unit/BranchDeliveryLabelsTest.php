<?php

namespace Tests\Unit;

use App\Models\BranchReceivingInvoiceItem;
use App\Support\BranchDeliveryLabels;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BranchDeliveryLabelsTest extends TestCase
{
    #[Test]
    public function old_cake_shipment_descriptions_are_shown_in_arabic_without_guessing_product_names(): void
    {
        $this->assertSame(
            'بستاشيو — وسط — دائري',
            BranchDeliveryLabels::itemDescription('بستاشيو — وسط — round')
        );
        $this->assertSame(
            'كيك خاص — شوكولاتة — كبير — نوتيلا — رأس قلب',
            BranchDeliveryLabels::itemDescription('كيك خاص — chocolate — large — nutella — heart_head')
        );
        $this->assertSame(
            'Deluxe Round Cake — دائري',
            BranchDeliveryLabels::itemDescription('Deluxe Round Cake — round')
        );
        $this->assertSame(
            'بستاشيو — وسط — دائري',
            (new BranchReceivingInvoiceItem(['description' => 'بستاشيو — وسط — round']))->displayDescription()
        );
    }

    #[Test]
    public function english_unit_codes_are_translated_but_invoice_snapshot_fields_are_unchanged(): void
    {
        $line = new BranchReceivingInvoiceItem([
            'description' => 'روشيه — صغير — round',
            'unit' => 'pcs',
        ]);
        $this->assertSame('حبة', $line->displayUnit());
        $this->assertSame('روشيه — صغير — دائري', $line->displayDescription());
        $this->assertSame('روشيه — صغير — round', $line->description);
        $this->assertSame('pcs', $line->unit);

        $this->assertSame('وحدة', BranchDeliveryLabels::unit('units'));
        $this->assertSame('صينية', BranchDeliveryLabels::unit('tray'));
        $this->assertSame('كغم', BranchDeliveryLabels::unit('kg'));
        $this->assertSame('حبة', BranchDeliveryLabels::unit('piece'));
        $this->assertSame('قطعة خاصة', BranchDeliveryLabels::unit('قطعة خاصة'));
    }
}
