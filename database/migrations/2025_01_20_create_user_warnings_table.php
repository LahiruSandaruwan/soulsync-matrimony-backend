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
        // Create warning templates first
        Schema::create('warning_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Template name
            $table->string('category'); // inappropriate_content, fake_profile, etc.
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->string('title'); // Warning title
            $table->text('message'); // Template message
            $table->integer('points')->default(1); // Default points for this warning
            $table->json('restrictions')->nullable(); // Default restrictions
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'severity']);
            $table->index('is_active');
        });

        Schema::create('user_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('issued_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('template_id')->nullable()->constrained('warning_templates')->onDelete('set null');
            $table->unsignedBigInteger('report_id')->nullable(); // References reports table
            $table->string('reason');
            $table->text('description')->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->integer('points')->default(1); // Warning points
            $table->boolean('acknowledged')->default(false);
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable(); // Additional warning details
            $table->timestamps();

            $table->index(['user_id', 'severity', 'created_at']);
            $table->index(['issued_by', 'created_at']);
            $table->index(['expires_at']);
            $table->index('report_id');
            $table->index('template_id');
        });

        // Add warning tracking fields to users table
        Schema::table('users', function (Blueprint $table) {
            $table->integer('warning_count')->default(0);
            $table->timestamp('last_warning_at')->nullable();
            $table->integer('warning_points')->default(0);
            $table->timestamp('restricted_until')->nullable();
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