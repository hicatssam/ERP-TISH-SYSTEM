<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShowroomDailyReport extends Model
{
    protected $fillable = [
        'location_id',
        'business_date',
        'status',
        'notes',
        'credit_debt_amount',
        'empty_packages_balance',
        'tahlayat_amount',
        'customer_discount_total',
        'prepaid_discount_amount',
        'reservations_amount',
        'report_total_amount',
        'debts_amount',
        'trays_packages_balance',
        'customer_treats_amount',
        'customer_discount_amount',
        'previous_prepaid_amount',
        'accounting_total_primary',
        'deferred_payment_amount',
        'special_reservations_amount',
        'debt_repayment_amount',
        'accounting_total_secondary',
        'previous_package_balance',
        'report_sales_total',
        'financial_report_total',
        'cashier_balance_total',
        'net_booking_total',
        'returned_for_correction_at',
        'created_by',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'closed_by',
        'closed_at',
        'reopened_by',
        'reopened_at',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'credit_debt_amount' => 'decimal:2',
            'empty_packages_balance' => 'decimal:2',
            'tahlayat_amount' => 'decimal:2',
            'customer_discount_total' => 'decimal:2',
            'prepaid_discount_amount' => 'decimal:2',
            'reservations_amount' => 'decimal:2',
            'report_total_amount' => 'decimal:2',
            'debts_amount' => 'decimal:2',
            'trays_packages_balance' => 'decimal:2',
            'customer_treats_amount' => 'decimal:2',
            'customer_discount_amount' => 'decimal:2',
            'previous_prepaid_amount' => 'decimal:2',
            'accounting_total_primary' => 'decimal:2',
            'deferred_payment_amount' => 'decimal:2',
            'special_reservations_amount' => 'decimal:2',
            'debt_repayment_amount' => 'decimal:2',
            'accounting_total_secondary' => 'decimal:2',
            'previous_package_balance' => 'decimal:2',
            'report_sales_total' => 'decimal:2',
            'financial_report_total' => 'decimal:2',
            'cashier_balance_total' => 'decimal:2',
            'net_booking_total' => 'decimal:2',
            'returned_for_correction_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShowroomDailyReportItem::class, 'report_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['closed', 'approved'], true);
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
