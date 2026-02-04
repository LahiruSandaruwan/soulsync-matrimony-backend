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
        Schema::create('success_stories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_user1_id')->constrained('users')->onDelete('cascade'); // Submitter
            $table->foreignId('couple_user2_id')->nullable()->constrained('users')->onDelete('set null'); // Partner
            $table->string('title', 200);
            $table->longText('description');
            $table->text('how_they_met')->nullable();
            $table->string('story_location', 255)->nullable();
            $table->date('marriage_date')->nullable();
            $table->string('cover_photo_path', 500)->nullable();
            $table->json('couple_info')->nullable(); // Additional couple details
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected'])->default('draft');
            $table->text('rejection_reason')->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->boolean('featured')->default(false);
            $table->timestamp('featured_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->unsignedInteger('share_count')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            // Indexes for common queries
            $table->index('status');
            $table->index('featured');
            $table->index(['status', 'featured']);
            $table->index('created_at');
        });

        Schema::create('success_story_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('success_story_id')->constrained('success_stories')->onDelete('cascade');
            $table->string('file_path', 500);
            $table->string('thumbnail_path', 500)->nullable();
            $table->string('medium_path', 500)->nullable();
            $table->string('original_filename', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->string('caption', 500)->nullable();
            $table->boolean('is_cover_photo')->default(false);
            $table->timestamps();

            // Index for sorting
            $table->index(['success_story_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('success_story_photos');
        Schema::dropIfExists('success_stories');
    }
};
