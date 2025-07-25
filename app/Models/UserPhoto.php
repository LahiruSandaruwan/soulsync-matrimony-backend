<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'original_filename',
        'file_path',
        'thumbnail_path',
        'medium_path',
        'large_path',
        'mime_type',
        'file_size',
        'width',
        'height',
        'is_profile_picture',
        'is_private',
        'sort_order',
        'caption',
        'status',
        'rejection_reason',
        'admin_notes',
        'moderated_by',
        'moderated_at',
        'upload_ip',
        'visible_to',
        'view_count',
        'last_viewed_at',
        'ai_analysis',
        'quality_score',
        'contains_face',
        'is_appropriate',
        'detected_objects',
        'exif_data',
        'photo_taken_at',
        'photo_latitude',
        'photo_longitude',
        'is_premium_photo',
        'watermark_removed',
        'premium_filters',
    ];

    protected $casts = [
        'is_profile_picture' => 'boolean',
        'is_private' => 'boolean',
        'visible_to' => 'array',
        'ai_analysis' => 'array',
        'detected_objects' => 'array',
        'exif_data' => 'array',
        'premium_filters' => 'array',
        'moderated_at' => 'datetime',
        'last_viewed_at' => 'datetime',
        'photo_taken_at' => 'datetime',
        'quality_score' => 'decimal:2',
        'contains_face' => 'boolean',
        'is_appropriate' => 'boolean',
        'is_premium_photo' => 'boolean',
        'watermark_removed' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function moderatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }
}
