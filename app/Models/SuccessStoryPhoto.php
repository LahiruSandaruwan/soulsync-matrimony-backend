<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SuccessStoryPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'success_story_id',
        'file_path',
        'thumbnail_path',
        'medium_path',
        'original_filename',
        'mime_type',
        'file_size',
        'width',
        'height',
        'sort_order',
        'caption',
        'is_cover_photo',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'sort_order' => 'integer',
        'is_cover_photo' => 'boolean',
    ];

    // Relationships
    public function successStory(): BelongsTo
    {
        return $this->belongsTo(SuccessStory::class);
    }

    // Accessors
    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? asset('storage/' . $this->file_path) : null;
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if ($this->thumbnail_path) {
            return asset('storage/' . $this->thumbnail_path);
        }
        return $this->file_url; // Fallback to original
    }

    public function getMediumUrlAttribute(): ?string
    {
        if ($this->medium_path) {
            return asset('storage/' . $this->medium_path);
        }
        return $this->file_url; // Fallback to original
    }

    // Methods
    public function deleteFiles(): bool
    {
        $paths = array_filter([
            $this->file_path,
            $this->thumbnail_path,
            $this->medium_path,
        ]);

        foreach ($paths as $path) {
            Storage::disk('public')->delete($path);
        }

        return true;
    }

    public function setAsCoverPhoto(): bool
    {
        // Remove cover photo flag from all other photos in this story
        $this->successStory->photos()
            ->where('id', '!=', $this->id)
            ->update(['is_cover_photo' => false]);

        // Update the story's cover photo path
        $this->successStory->update(['cover_photo_path' => $this->file_path]);

        return $this->update(['is_cover_photo' => true]);
    }

    // Boot method to handle deletion
    protected static function booted(): void
    {
        static::deleting(function (SuccessStoryPhoto $photo) {
            $photo->deleteFiles();
        });
    }
}
