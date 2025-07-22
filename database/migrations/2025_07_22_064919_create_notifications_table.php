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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Recipient
            $table->foreignId('actor_id')->nullable()->constrained('users')->onDelete('set null'); // Who caused the notification
            
            // Notification content
            $table->string('type', 50); // match, message, like, profile_view, etc.
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Additional data (user_id, match_id, etc.)
            
            // Status
            $table->enum('status', ['unread', 'read', 'archived', 'deleted'])->default('unread');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            
            // Priority and category
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('category', [
                'match', 'message', 'like', 'super_like', 'profile_view', 
                'subscription', 'payment', 'system', 'admin', 'promotion'
            ]);
            
            // Delivery channels
            $table->boolean('sent_in_app')->default(true);
            $table->boolean('sent_email')->default(false);
            $table->boolean('sent_push')->default(false);
            $table->boolean('sent_sms')->default(false);
            
            // Delivery tracking
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamp('push_sent_at')->nullable();
            $table->timestamp('sms_sent_at')->nullable();
            $table->json('delivery_status')->nullable(); // Success/failure for each channel
            
            // Actions and interactions
            $table->json('actions')->nullable(); // Available actions (view profile, reply, etc.)
            $table->string('action_taken')->nullable(); // Which action was clicked
            $table->timestamp('action_taken_at')->nullable();
            $table->string('action_url')->nullable(); // Deep link or URL
            
            // Grouping and batching
            $table->string('group_key')->nullable(); // For grouping similar notifications
            $table->boolean('is_grouped')->default(false);
            $table->integer('group_count')->default(1); // How many notifications in this group
            
            // Expiration and cleanup
            $table->timestamp('expires_at')->nullable(); // When notification becomes irrelevant
            $table->boolean('is_persistent')->default(false); // Don't auto-delete
            
            // A/B testing and analytics
            $table->string('campaign_id')->nullable(); // For promotional notifications
            $table->json('analytics_data')->nullable(); // Tracking data
            $table->boolean('click_tracked')->default(false);
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['user_id', 'category', 'status']);
            $table->index(['type', 'created_at']);
            $table->index(['priority', 'status']);
            $table->index(['actor_id']);
            $table->index(['group_key']);
            $table->index(['expires_at']);
            $table->index(['sent_push', 'push_sent_at']);
            $table->index(['sent_email', 'email_sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
