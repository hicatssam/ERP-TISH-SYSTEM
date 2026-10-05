<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShowroomCakeDailyReportItem extends Model
{
    protected $fillable = [
        'report_id',
        'category_key',
        'category_name',
        'sort_order',
        'opening_qty',
        'received_qty',
        'total_qty',
        'sold_qty',
        'actual_closing_qty',
        'variance_qty',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'opening_qty' => 'integer',
            'received_qty' => 'integer',
            'total_qty' => 'integer',
            'sold_qty' => 'integer',
            'actual_closing_qty' => 'integer',
            'variance_qty' => 'integer',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(ShowroomCakeDailyReport::class, 'report_id');
    }
}
