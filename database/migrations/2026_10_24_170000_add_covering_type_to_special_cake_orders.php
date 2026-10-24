<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('special_cake_orders', function (Blueprint $table) {
            $table->string('covering_type', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('special_cake_orders', function (Blueprint $table) {
            $table->dropColumn('covering_type');
        });
    }
};
