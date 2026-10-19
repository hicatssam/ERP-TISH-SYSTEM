<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchShipmentTripItem extends Model
{
    protected $fillable = [
        'branch_shipment_trip_id', 'branch_receiving_invoice_item_id',
        'loaded_quantity', 'received_quantity', 'note',
    ];

    protected function casts(): array
    {
        return ['loaded_quantity' => 'decimal:3', 'received_quantity' => 'decimal:3'];
    }

    public function trip(): BelongsTo { return $this->belongsTo(BranchShipmentTrip::class, 'branch_shipment_trip_id'); }
    public function invoiceItem(): BelongsTo { return $this->belongsTo(BranchReceivingInvoiceItem::class, 'branch_receiving_invoice_item_id'); }
}
