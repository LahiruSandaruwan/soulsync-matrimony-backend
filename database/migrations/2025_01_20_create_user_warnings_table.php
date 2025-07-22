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
        Schema::create('user_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('issued_by')->constrained('users')->onDelete('cascade'); // Admin who issued warning
            $table->foreignId('report_id')->nullable()->constrained()->onDelete('set null'); // Related report if any
            $table->enum('severity', ['minor', 'moderate', 'major', 'severe']);
            $table->string('category'); // inappropriate_content, fake_profile, harassment, etc.
            $table->string('title');
            $table->text('reason');
            $table->text('evidence')->nullable(); // URLs, screenshots, etc.
            $table->json('restrictions')->nullable(); // Temporary restrictions applied
            $table->boolean('acknowledged')->default(false);
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // For temporary restrictions
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['severity', 'created_at']);
            $table->index(['expires_at']);
        });

        Schema::create('warning_templates', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->enum('severity', ['minor', 'moderate', 'major', 'severe']);
            $table->string('title');
            $table->text('default_message');
            $table->json('default_restrictions')->nullable();
            $table->integer('escalation_after_count')->default(3); // Escalate after X warnings
            $table->enum('escalation_action', ['suspend', 'ban', 'review'])->default('suspend');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'severity']);
        });

        // Add warning-related fields to users table
        Schema::table('users', function (Blueprint $table) {
            $table->integer('warning_count')->default(0)->after('status');
            $table->timestamp('last_warning_at')->nullable()->after('warning_count');
            $table->integer('warning_points')->default(0)->after('last_warning_at');
            $table->timestamp('restricted_until')->nullable()->after('warning_points');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['warning_count', 'last_warning_at', 'warning_points', 'restricted_until']);
        });
        
        Schema::dropIfExists('warning_templates');
        Schema::dropIfExists('user_warnings');
    }
}; 