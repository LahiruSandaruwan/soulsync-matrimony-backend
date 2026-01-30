<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create audit logs table for tracking sensitive operations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Who performed the action
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('user_email')->nullable(); // Store email in case user is deleted
            $table->string('user_type')->default('user'); // user, admin, system

            // What was done
            $table->string('action', 50); // create, update, delete, login, logout, etc.
            $table->string('entity_type', 100); // User, Profile, Subscription, etc.
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('description');

            // What changed
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable(); // Additional context

            // Request context
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('request_method', 10)->nullable();
            $table->string('request_url')->nullable();

            // Categorization
            $table->enum('severity', ['info', 'warning', 'critical'])->default('info');
            $table->enum('category', [
                'auth',           // Login, logout, password change
                'profile',        // Profile updates
                'subscription',   // Payment, plan changes
                'admin',          // Admin actions
                'moderation',     // Reports, warnings, bans
                'data',           // Data exports, deletions (GDPR)
                'security',       // 2FA, suspicious activity
                'system',         // System events
            ]);

            $table->timestamps();

            // Indexes for querying
            $table->index(['user_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['action', 'created_at']);
            $table->index(['category', 'severity', 'created_at']);
            $table->index('ip_address');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
