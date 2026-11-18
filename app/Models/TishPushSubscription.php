<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TishPushSubscription extends Model
{
    protected $fillable = [
        'order_id', 'endpoint', 'endpoint_hash', 'p256dh', 'auth_secret', 'last_state',
    ];

    protected function casts(): array
    {
        return [
            'endpoint' => 'encrypted',
            'p256dh' => 'encrypted',
            'auth_secret' => 'encrypted',
        ];
    }

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
