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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('receiver_id')->constrained('users')->onDelete('cascade');
            
            // Message content
            $table->text('message')->nullable(); // Text content
            $table->enum('type', ['text', 'image', 'audio', 'video', 'file', 'system', 'gift'])->default('text');
            $table->json('media_files')->nullable(); // Array of file paths/URLs
            $table->json('metadata')->nullable(); // Additional message data (duration, file size, etc.)
            
            // Message status
            $table->enum('status', ['sent', 'delivered', 'read', 'failed', 'deleted'])->default('sent');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->enum('deleted_by', ['sender', 'receiver', 'both', 'admin'])->nullable();
            
            // Reply/Thread functionality
            $table->foreignId('reply_to_id')->nullable()->constrained('messages')->onDelete('set null');
            $table->text('quoted_message')->nullable(); // Copy of the message being replied to
            
            // Premium features
            $table->boolean('is_premium_message')->default(false);
            $table->boolean('is_priority')->default(false);
            $table->json('premium_features')->nullable(); // Array of premium features used
            
            // Moderation
            $table->boolean('is_flagged')->default(false);
            $table->text('flag_reason')->nullable();
            $table->foreignId('flagged_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('flagged_at')->nullable();
            $table->boolean('is_approved')->default(true);
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Analytics
            $table->integer('character_count')->default(0);
            $table->json('sentiment_analysis')->nullable(); // AI sentiment analysis
            $table->boolean('contains_contact_info')->default(false); // AI detection of phone/email
            
            // System messages
            $table->json('system_data')->nullable(); // For system-generated messages
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['conversation_id', 'created_at']);
            $table->index(['sender_id', 'created_at']);
            $table->index(['receiver_id', 'status']);
            $table->index(['type', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['is_flagged', 'is_approved']);
            $table->index(['reply_to_id']);
            $table->index(['deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
