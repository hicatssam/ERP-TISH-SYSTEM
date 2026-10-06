<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('showroom_daily_report_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('showroom_daily_report_items', 'takeaway_sales_kg')) {
                $table->decimal('takeaway_sales_kg', 12, 3)
                    ->default(0)
                    ->after('sales_kg');
            }
        });
    }

    public function down(): void
    {
        Schema::table('showroom_daily_report_items', function (Blueprint $table): void {
            if (Schema::hasColumn('showroom_daily_report_items', 'takeaway_sales_kg')) {
                $table->dropColumn('takeaway_sales_kg');
            }
        });
    }
};
