<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BranchReceivingInvoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'branch_shipment_id',
        'source_type',
        'source_id',
        'sending_location_id',
        'receiving_location_id',
        'driver_id',
        'received_by',
        'status',
        'total_items_sent',
        'total_items_received',
        'total_difference',
        'notes',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'total_items_sent' => 'decimal:3',
            'total_items_received' => 'decimal:3',
            'total_difference' => 'decimal:3',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(BranchShipment::class, 'branch_shipment_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function sendingLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'sending_location_id');
    }

    public function receivingLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'receiving_location_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BranchReceivingInvoiceItem::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }
}
