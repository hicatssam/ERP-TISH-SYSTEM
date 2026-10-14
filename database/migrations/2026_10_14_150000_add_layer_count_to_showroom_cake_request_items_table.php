<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('showroom_cake_request_items', function (Blueprint $table): void {
            // Nullable so historical requests do not gain an invented layer count.
            $table->unsignedTinyInteger('layer_count')->nullable()->after('cake_size');
        });
    }

    public function down(): void
    {
        Schema::table('showroom_cake_request_items', function (Blueprint $table): void {
            $table->dropColumn('layer_count');
        });
    }
};
