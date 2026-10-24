<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShowroomSweetsRequestItem extends Model
{
    protected $fillable = [
        'showroom_sweets_request_id',
        'product_id',
        'product_name_snapshot',
        'quantity',
        'reserved_quantity',
        'reservation_notes',
        'requested_unit',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'reserved_quantity' => 'decimal:3',
        ];
    }

    public function availableQuantity(): float
    {
        return max(
            0,
            (float) $this->quantity
                - (float) $this->reserved_quantity
        );
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ShowroomSweetsRequest::class, 'showroom_sweets_request_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function displayRequestedUnit(): string
    {
        $value = trim((string) $this->requested_unit);

        return [
            'pcs' => 'حبة', 'pc' => 'حبة', 'piece' => 'حبة', 'pieces' => 'حبة',
            'unit' => 'حبة', 'units' => 'حبة', 'each' => 'حبة',
            'tray' => 'صينية', 'trays' => 'صينية',
            'box' => 'علبة', 'boxes' => 'علبة',
            'kg' => 'كغم', 'kgs' => 'كغم', 'kilogram' => 'كغم', 'kilograms' => 'كغم',
            'bundle' => 'ربطة', 'bundles' => 'ربطة',
            'plate' => 'صدر', 'plates' => 'صدر',
        ][strtolower($value)] ?? ($value ?: 'حبة');
    }

    public function displayProductName(): string
    {
        return $this->product?->name_ar
            ?? $this->product?->name
            ?? $this->product_name_snapshot
            ?? 'منتج محذوف';
    }
}
