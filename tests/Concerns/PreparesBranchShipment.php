<?php

namespace Tests\Concerns;

use App\Models\BranchShipment;
use App\Models\Category;
use App\Models\Product;
use App\Models\ShowroomCakeRequest;
use App\Models\ShowroomSweetsRequest;
use App\Models\User;
use App\Services\Delivery\BranchDeliveryService;

trait PreparesBranchShipment
{
    private function arrivedShipmentFor(
        ShowroomCakeRequest|ShowroomSweetsRequest $source,
        User $factoryActor
    ): BranchShipment {
        if (! $source->items()->exists()) {
            if ($source instanceof ShowroomCakeRequest) {
                $source->items()->create(['cake_type' => 'Test cake', 'quantity' => 2]);
            } else {
                $category = Category::firstOrCreate(['slug' => 'receipt-test'], [
                    'name' => 'Receipt test sweets', 'is_active' => true,
                ]);
                $product = Product::query()->create([
                    'category_id' => $category->id,
                    'name' => 'Receipt test sweets',
                    'sku' => 'RECEIPT-'.$source->id,
                    'barcode' => 'RECEIPT-'.$source->id,
                    'unit_id' => \App\Models\Unit::where('code', 'piece')->firstOrFail()->id,
                    'product_type' => 'standard',
                    'is_active' => true,
                ]);
                $source->items()->create([
                    'product_id' => $product->id,
                    'product_name_snapshot' => $product->name,
                    'quantity' => 2,
                    'requested_unit' => 'piece',
                ]);
            }
        }

        $delivery = app(BranchDeliveryService::class);
        $shipment = $delivery->createShipment($source->fresh(), $factoryActor);
        // Historical tests exercise the original one-step shipment lifecycle.
        $shipment->update(['multi_trip_enabled' => false]);
        $driver = User::factory()->create();
        $shipment = $delivery->markLoaded($shipment, $factoryActor, $driver);
        $shipment = $delivery->dispatch($shipment, $factoryActor);

        return $delivery->markArrived($shipment, $driver);
    }

    private function fullReceiptItems(BranchShipment $shipment): array
    {
        return $shipment->receivingInvoice->items->mapWithKeys(
            fn ($item) => [$item->id => ['received_quantity' => $item->sent_quantity]]
        )->all();
    }
}
