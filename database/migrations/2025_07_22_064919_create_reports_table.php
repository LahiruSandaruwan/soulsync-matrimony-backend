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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->onDelete('cascade'); // Who reported
            $table->foreignId('reported_user_id')->constrained('users')->onDelete('cascade'); // Who was reported
            
            // Report details
            $table->enum('type', [
                'inappropriate_content', 'fake_profile', 'harassment', 'spam', 
                'inappropriate_photos', 'scam', 'violence_threat', 'hate_speech',
                'underage', 'married_person', 'duplicate_account', 'other'
            ]);
            $table->text('description'); // Detailed description from reporter
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            
            // Evidence
            $table->json('evidence_photos')->nullable(); // Screenshots or photo evidence
            $table->json('evidence_messages')->nullable(); // Chat messages as evidence
            $table->json('evidence_data')->nullable(); // Additional evidence metadata
            
            // Context
            $table->string('reported_content_type')->nullable(); // profile, photo, message, etc.
            $table->bigInteger('reported_content_id')->nullable(); // ID of specific content
            $table->json('context_data')->nullable(); // Additional context information
            
            // Status and handling
            $table->enum('status', ['pending', 'under_review', 'resolved', 'dismissed', 'escalated'])
                  ->default('pending');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            
            // Moderation
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null'); // Moderator
            $table->timestamp('assigned_at')->nullable();
            $table->text('moderator_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Resolution
            $table->enum('resolution', [
                'no_action', 'warning_sent', 'profile_suspended', 'account_banned',
                'content_removed', 'profile_restricted', 'investigation_needed'
            ])->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Actions taken
            $table->json('actions_taken')->nullable(); // Array of actions performed
            $table->boolean('user_notified')->default(false); // If reported user was notified
            $table->boolean('reporter_notified')->default(false); // If reporter was updated
            
            // Follow-up
            $table->boolean('requires_followup')->default(false);
            $table->timestamp('followup_date')->nullable();
            $table->text('followup_notes')->nullable();
            
            // Legal and compliance
            $table->boolean('legal_concern')->default(false);
            $table->boolean('law_enforcement_notified')->default(false);
            $table->text('legal_notes')->nullable();
            
            // Analytics and patterns
            $table->json('patterns_detected')->nullable(); // AI-detected patterns
            $table->integer('similar_reports_count')->default(0); // Related reports count
            $table->boolean('is_repeat_offender')->default(false);
            $table->boolean('is_serial_reporter')->default(false);
            
            $table->timestamps();
            
            // Indexes for moderation dashboard
            $table->index(['status', 'priority', 'created_at']);
            $table->index(['reported_user_id', 'status']);
            $table->index(['reporter_id', 'created_at']);
            $table->index(['type', 'severity']);
            $table->index(['assigned_to', 'status']);
            $table->index(['requires_followup', 'followup_date']);
            $table->index(['legal_concern']);
            $table->index(['is_repeat_offender']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
