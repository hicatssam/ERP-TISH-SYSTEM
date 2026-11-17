<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantDiningGroup extends Model
{
    protected $fillable = [
        'location_id', 'name', 'guest_count', 'opened_by', 'closed_by',
        'status', 'opened_at', 'closed_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'guest_count' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(RestaurantTableSession::class, 'restaurant_dining_group_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
