<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchReceivingInvoiceItem extends Model
{
    protected $fillable = [
        'branch_receiving_invoice_id',
        'source_item_id',
        'description',
        'unit',
        'sent_quantity',
        'received_quantity',
        'difference_quantity',
        'note',
        'metadata',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sent_quantity' => 'decimal:3',
            'received_quantity' => 'decimal:3',
            'difference_quantity' => 'decimal:3',
            'metadata' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BranchReceivingInvoice::class, 'branch_receiving_invoice_id');
    }
}
