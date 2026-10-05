<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShowroomDailyReportItem extends Model
{
    protected $fillable = [
        'report_id',
        'product_id',
        'product_name',
        'sort_order',
        'opening_kg',
        'received_trays',
        'received_kg',
        'sales_kg',
        'damaged_kg',
        'returned_kg',
        'other_net_kg',
        'expected_closing_kg',
        'actual_closing_kg',
        'variance_kg',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'opening_kg' => 'decimal:3',
            'received_trays' => 'decimal:2',
            'received_kg' => 'decimal:3',
            'sales_kg' => 'decimal:3',
            'damaged_kg' => 'decimal:3',
            'returned_kg' => 'decimal:3',
            'other_net_kg' => 'decimal:3',
            'expected_closing_kg' => 'decimal:3',
            'actual_closing_kg' => 'decimal:3',
            'variance_kg' => 'decimal:3',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(ShowroomDailyReport::class, 'report_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
