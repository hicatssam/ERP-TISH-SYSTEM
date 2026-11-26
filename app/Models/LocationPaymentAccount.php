<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class LocationPaymentAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_id',
        'payment_method_id',
        'name',
        'name_en',
        'provider_name',
        'provider_name_en',
        'account_holder_name',
        'account_holder_name_en',
        'account_number',
        'iban',
        'phone_number',
        'instructions',
        'instructions_en',
        'is_active',
        'can_disburse',
        'is_customer_visible',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'can_disburse' => 'boolean',
            'is_customer_visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}