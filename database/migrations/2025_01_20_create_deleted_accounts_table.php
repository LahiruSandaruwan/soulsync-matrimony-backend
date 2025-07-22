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
        Schema::create('deleted_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_user_id');
            $table->string('email');
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('country_code', 10)->nullable();
            $table->timestamp('registration_date')->useCurrent();
            $table->timestamp('deletion_date')->useCurrent();
            $table->enum('deleted_by', ['user', 'admin', 'system'])->default('user');
            $table->text('deletion_reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('profile_data')->nullable(); // Store profile snapshot
            $table->json('subscription_data')->nullable(); // Store subscription history
            $table->integer('total_matches')->default(0);
            $table->integer('total_messages')->default(0);
            $table->integer('total_photos')->default(0);
            $table->decimal('total_spent', 10, 2)->default(0);
            $table->boolean('data_exported')->default(false);
            $table->timestamp('data_exported_at')->nullable();
            $table->timestamp('permanent_deletion_at')->nullable(); // For GDPR compliance
            $table->timestamps();

            $table->index(['original_user_id']);
            $table->index(['email']);
            $table->index(['deletion_date']);
            $table->index(['deleted_by']);
            $table->index(['permanent_deletion_at']);
        });

        Schema::create('deleted_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_user_id');
            $table->string('email');
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->json('user_data')->nullable(); // Complete user snapshot
            $table->timestamp('deletion_date')->useCurrent();
            $table->enum('deleted_by', ['admin', 'system'])->default('admin');
            $table->unsignedBigInteger('deleted_by_admin_id')->nullable();
            $table->text('admin_reason')->nullable();
            $table->text('admin_notes')->nullable();
            $table->boolean('banned')->default(false);
            $table->timestamp('ban_expires_at')->nullable();
            $table->boolean('can_reactivate')->default(true);
            $table->timestamp('reactivation_deadline')->nullable();
            $table->timestamps();

            $table->index(['original_user_id']);
            $table->index(['email']);
            $table->index(['deletion_date']);
            $table->index(['deleted_by_admin_id']);
            $table->index(['banned']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deleted_users');
        Schema::dropIfExists('deleted_accounts');
    }
}; 