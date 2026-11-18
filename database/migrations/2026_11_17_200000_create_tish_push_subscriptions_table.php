<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tish_push_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->text('endpoint');
            $table->char('endpoint_hash', 64);
            $table->unique(['order_id', 'endpoint_hash'], 'tish_push_order_endpoint_unique');
            $table->text('p256dh');
            $table->text('auth_secret');
            $table->string('last_state', 40);
            $table->timestamps();
            $table->index(['order_id', 'last_state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tish_push_subscriptions');
    }
};
