<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_raw_material')->default(false)->index();
        });
        Schema::table('stock_requests', function (Blueprint $table): void {
            $table->foreignId('showroom_sweets_request_id')->nullable()
                ->constrained('showroom_sweets_requests')->restrictOnDelete();
            $table->foreignId('showroom_cake_request_id')->nullable()
                ->constrained('showroom_cake_requests')->restrictOnDelete();
            $table->unique('showroom_sweets_request_id', 'stock_request_sweets_unique');
            $table->unique('showroom_cake_request_id', 'stock_request_cake_unique');
        });
    }
    public function down(): void
    {
        Schema::table('stock_requests', function (Blueprint $table): void {
            $table->dropUnique('stock_request_sweets_unique');
            $table->dropUnique('stock_request_cake_unique');
            $table->dropConstrainedForeignId('showroom_sweets_request_id');
            $table->dropConstrainedForeignId('showroom_cake_request_id');
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['is_raw_material']);
            $table->dropColumn('is_raw_material');
        });
    }
};
