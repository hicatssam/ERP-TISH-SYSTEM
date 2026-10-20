<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BranchShipment extends Model
{
    public const PENDING_LOADING = 'pending_loading';
    public const LOADED = 'loaded';
    public const OUT_FOR_DELIVERY = 'out_for_delivery';
    public const ARRIVED = 'arrived';
    public const RECEIVED = 'received';
    public const CLOSED_SHORT = 'closed_short';

    protected $fillable = [
        'source_type',
        'source_id',
        'from_location_id',
        'to_location_id',
        'status',
        'multi_trip_enabled',
        'planned_loading_at',
        'planned_receiving_at',
        'driver_id',
        'loaded_by',
        'loaded_at',
        'dispatched_by',
        'dispatched_at',
        'arrived_by',
        'arrived_at',
        'received_by',
        'received_at',
        'closed_by',
        'closed_at',
        'closure_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'multi_trip_enabled' => 'boolean',
            'planned_loading_at' => 'datetime',
            'planned_receiving_at' => 'datetime',
            'loaded_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'arrived_at' => 'datetime',
            'received_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function loadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'loaded_by');
    }

    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function arrivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'arrived_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function trips(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BranchShipmentTrip::class)->orderBy('trip_number');
    }

    public function receivingInvoice(): HasOne
    {
        return $this->hasOne(BranchReceivingInvoice::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::PENDING_LOADING => 'بانتظار التحميل',
            self::LOADED => 'تم التحميل',
            self::OUT_FOR_DELIVERY => 'خرج للتوصيل',
            self::ARRIVED => 'وصل إلى الفرع',
            self::RECEIVED => 'تم استلام الفرع',
            self::CLOSED_SHORT => 'مغلقة بنقص',
            default => $this->status,
        };
    }
}
