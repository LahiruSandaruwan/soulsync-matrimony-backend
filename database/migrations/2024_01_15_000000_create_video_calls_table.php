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
        Schema::create('video_calls', function (Blueprint $table) {
            $table->id();
            
            // Participants
            $table->foreignId('caller_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('callee_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->onDelete('set null');
            
            // Call identifiers
            $table->string('call_id')->unique();
            $table->string('room_id')->nullable();
            $table->text('caller_token')->nullable();
            $table->text('callee_token')->nullable();
            
            // Call status and timing
            $table->enum('status', ['pending', 'accepted', 'rejected', 'ended', 'missed'])->default('pending');
            $table->timestamp('initiated_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            
            // Call metadata
            $table->enum('end_reason', ['normal', 'network_issue', 'technical_issue', 'user_ended', 'timeout'])->nullable();
            $table->tinyInteger('quality_rating')->nullable()->comment('1-5 rating');
            $table->text('feedback')->nullable();
            $table->string('recording_url')->nullable();
            $table->json('metadata')->nullable(); // For storing additional call data
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['caller_id', 'status']);
            $table->index(['callee_id', 'status']);
            $table->index(['status', 'initiated_at']);
            $table->index(['conversation_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_calls');
    }
};
