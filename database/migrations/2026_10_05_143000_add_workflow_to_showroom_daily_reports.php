<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('showroom_daily_reports', function (Blueprint $table): void {
            $table->foreignId('submitted_by')
                ->nullable()
                ->after('created_by')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('submitted_at')
                ->nullable()
                ->after('submitted_by');

            $table->foreignId('reviewed_by')
                ->nullable()
                ->after('submitted_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')
                ->nullable()
                ->after('reviewed_by');

            $table->text('review_notes')
                ->nullable()
                ->after('reviewed_at');
        });

        Schema::table('showroom_daily_report_items', function (Blueprint $table): void {
            $table->decimal('other_net_kg', 12, 3)
                ->default(0)
                ->after('returned_kg');
        });
    }

    public function down(): void
    {
        Schema::table('showroom_daily_report_items', function (Blueprint $table): void {
            $table->dropColumn('other_net_kg');
        });

        Schema::table('showroom_daily_reports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropColumn('submitted_at');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['reviewed_at', 'review_notes']);
        });
    }
};
