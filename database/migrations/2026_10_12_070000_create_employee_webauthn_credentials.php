<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_webauthn_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('credential_hash', 64)->unique();
            $table->text('credential_id');
            $table->text('public_key');
            $table->string('rp_id', 253);
            $table->string('label', 100);
            $table->unsignedBigInteger('sign_count')->default(0);
            $table->boolean('backup_eligible')->default(false);
            $table->boolean('backed_up')->default(false);
            $table->string('status', 20)->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'status']);
        });

        Schema::create('attendance_webauthn_challenges', function (Blueprint $table): void {
            $table->id();
            $table->char('token_hash', 64)->unique();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('session_hash', 64);
            $table->string('purpose', 20);
            $table->json('context');
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_webauthn_challenges');
        Schema::dropIfExists('employee_webauthn_credentials');
    }
};
