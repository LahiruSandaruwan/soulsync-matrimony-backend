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
        Schema::create('user_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // File details
            $table->string('original_filename');
            $table->string('file_path'); // Main image path
            $table->string('thumbnail_path')->nullable(); // Thumbnail version
            $table->string('medium_path')->nullable(); // Medium size version
            $table->string('large_path')->nullable(); // Large size version
            $table->string('mime_type', 50);
            $table->integer('file_size'); // Size in bytes
            $table->integer('width')->nullable(); // Image width
            $table->integer('height')->nullable(); // Image height
            
            // Photo settings
            $table->boolean('is_profile_picture')->default(false);
            $table->boolean('is_private')->default(false); // Premium feature
            $table->integer('sort_order')->default(0); // Display order
            $table->text('caption')->nullable(); // Photo description
            
            // Moderation
            $table->enum('status', ['pending', 'approved', 'rejected', 'flagged'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->text('admin_notes')->nullable(); // Admin/moderator notes
            $table->foreignId('moderated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('moderated_at')->nullable();
            $table->string('upload_ip', 45)->nullable(); // Uploader's IP address
            
            // Privacy and access
            $table->json('visible_to')->nullable(); // Array of user IDs who can see private photos
            $table->integer('view_count')->default(0); // How many times viewed
            $table->timestamp('last_viewed_at')->nullable();
            
            // AI analysis
            $table->json('ai_analysis')->nullable(); // AI content analysis results
            $table->decimal('quality_score', 3, 2)->nullable(); // AI quality rating (0-10)
            $table->boolean('contains_face')->nullable(); // Face detection result
            $table->boolean('is_appropriate')->default(true); // Content appropriateness
            $table->json('detected_objects')->nullable(); // AI object detection
            
            // EXIF and metadata
            $table->json('exif_data')->nullable(); // Camera EXIF data
            $table->timestamp('photo_taken_at')->nullable(); // When photo was taken
            $table->decimal('photo_latitude', 10, 8)->nullable(); // GPS coordinates
            $table->decimal('photo_longitude', 11, 8)->nullable();
            
            // Premium features
            $table->boolean('is_premium_photo')->default(false);
            $table->boolean('watermark_removed')->default(false);
            $table->json('premium_filters')->nullable(); // Applied premium filters
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'is_profile_picture']);
            $table->index(['user_id', 'sort_order']);
            $table->index(['status', 'created_at']);
            $table->index(['is_private']);
            $table->index(['moderated_by', 'moderated_at']);
            $table->index(['view_count']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_photos');
    }
};
