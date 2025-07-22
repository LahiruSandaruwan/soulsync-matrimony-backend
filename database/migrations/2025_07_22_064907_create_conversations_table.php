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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_one_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('user_two_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('match_id')->nullable()->constrained('user_matches')->onDelete('set null');
            
            // Conversation status
            $table->enum('status', ['active', 'archived', 'blocked', 'deleted'])->default('active');
            $table->enum('type', ['match', 'interest', 'premium'])->default('match');
            
            // Last message tracking
            $table->text('last_message')->nullable();
            $table->enum('last_message_type', ['text', 'image', 'audio', 'video', 'file'])->nullable();
            $table->foreignId('last_message_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('last_message_at')->nullable();
            
            // Read status tracking
            $table->timestamp('user_one_read_at')->nullable();
            $table->timestamp('user_two_read_at')->nullable();
            $table->integer('user_one_unread_count')->default(0);
            $table->integer('user_two_unread_count')->default(0);
            
            // Blocking and restrictions
            $table->foreignId('blocked_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('blocked_at')->nullable();
            $table->text('block_reason')->nullable();
            
            // Premium features
            $table->boolean('is_premium_conversation')->default(false);
            $table->boolean('priority_conversation')->default(false);
            $table->json('conversation_settings')->nullable(); // Custom settings
            
            // Analytics
            $table->integer('total_messages')->default(0);
            $table->timestamp('started_at')->nullable(); // When first message was sent
            $table->integer('days_active')->default(0); // Number of days with activity
            
            $table->timestamps();
            
            // Ensure unique conversations between two users
            $table->unique(['user_one_id', 'user_two_id']);
            
            // Indexes for performance
            $table->index(['user_one_id', 'status']);
            $table->index(['user_two_id', 'status']);
            $table->index(['last_message_at']);
            $table->index(['status', 'last_message_at']);
            $table->index(['match_id']);
            $table->index(['blocked_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
