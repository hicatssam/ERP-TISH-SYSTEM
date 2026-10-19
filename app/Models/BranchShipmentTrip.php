<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BranchShipmentTrip extends Model
{
    protected $fillable = [
        'branch_shipment_id', 'trip_number', 'driver_id', 'loaded_by', 'loaded_at',
        'received_by', 'received_at', 'status', 'notes', 'receiving_notes',
    ];

    protected function casts(): array
    {
        return ['loaded_at' => 'datetime', 'received_at' => 'datetime'];
    }

    public function shipment(): BelongsTo { return $this->belongsTo(BranchShipment::class, 'branch_shipment_id'); }
    public function items(): HasMany { return $this->hasMany(BranchShipmentTripItem::class, 'branch_shipment_trip_id'); }
    public function driver(): BelongsTo { return $this->belongsTo(User::class, 'driver_id'); }
    public function loadedBy(): BelongsTo { return $this->belongsTo(User::class, 'loaded_by'); }
    public function receivedBy(): BelongsTo { return $this->belongsTo(User::class, 'received_by'); }
}
