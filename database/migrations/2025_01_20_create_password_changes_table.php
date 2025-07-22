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
        Schema::create('password_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('old_password_hash')->nullable(); // Store hash for validation
            $table->string('new_password_hash');
            $table->string('changed_by')->default('user'); // user, admin, system
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('reason')->nullable(); // admin change reason
            $table->boolean('forced')->default(false); // forced password change
            $table->timestamp('expires_at')->nullable(); // if temporary password
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['changed_by']);
        });

        // Add last_password_change to users table
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_password_change')->nullable()->after('password');
            $table->boolean('password_expired')->default(false)->after('last_password_change');
            $table->timestamp('password_expires_at')->nullable()->after('password_expired');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['last_password_change', 'password_expired', 'password_expires_at']);
        });
        
        Schema::dropIfExists('password_changes');
    }
}; 