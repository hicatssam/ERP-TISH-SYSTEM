<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_banners', function (Blueprint $table) {
            // Optional: existing Arabic banners keep displaying unchanged.
            $table->string('title_en', 120)->nullable();
            $table->string('subtitle_en', 200)->nullable();
            $table->string('badge_text_en', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('menu_banners', function (Blueprint $table) {
            $table->dropColumn(['title_en', 'subtitle_en', 'badge_text_en']);
        });
    }
};
