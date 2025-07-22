<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('two_factor_auth', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->boolean('enabled')->default(false);
            $table->string('secret')->nullable(); // For TOTP/Google Authenticator
            $table->text('recovery_codes')->nullable(); // JSON array of backup codes
            $table->timestamp('enabled_at')->nullable();
            $table->string('method')->default('totp'); // totp, sms, email
            $table->string('phone')->nullable(); // For SMS 2FA
            $table->boolean('phone_verified')->default(false);
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['user_id', 'enabled']);
        });

        Schema::create('two_factor_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('code', 10);
            $table->string('type')->default('login'); // login, setup, disable
            $table->timestamp('expires_at');
            $table->boolean('used')->default(false);
            $table->timestamp('used_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'code', 'used']);
            $table->index(['expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('two_factor_codes');
        Schema::dropIfExists('two_factor_auth');
    }
}; 