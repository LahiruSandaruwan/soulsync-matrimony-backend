<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class SuccessStory extends Model
{
    use HasFactory;

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'couple_user1_id',
        'couple_user2_id',
        'title',
        'description',
        'how_they_met',
        'story_location',
        'marriage_date',
        'cover_photo_path',
        'couple_info',
        'status',
        'rejection_reason',
        'admin_notes',
        'approved_by',
        'approved_at',
        'featured',
        'featured_at',
        'view_count',
        'share_count',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'couple_info' => 'array',
        'marriage_date' => 'date',
        'approved_at' => 'datetime',
        'featured_at' => 'datetime',
        'featured' => 'boolean',
        'view_count' => 'integer',
        'share_count' => 'integer',
    ];

    // Relationships
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'couple_user1_id');
    }

    public function coupleUser2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'couple_user2_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(SuccessStoryPhoto::class)->orderBy('sort_order');
    }

    // Scopes
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('featured', true);
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('couple_user1_id', $userId);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->approved()->orderBy('featured', 'desc')->orderBy('created_at', 'desc');
    }

    // Methods
    public function approve(int $adminId, ?string $notes = null): bool
    {
        return $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $adminId,
            'approved_at' => now(),
            'admin_notes' => $notes,
            'rejection_reason' => null,
        ]);
    }

    public function reject(string $reason, ?string $notes = null): bool
    {
        return $this->update([
            'status' => self::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'admin_notes' => $notes,
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    public function submitForApproval(): bool
    {
        if ($this->status !== self::STATUS_DRAFT) {
            return false;
        }
        return $this->update(['status' => self::STATUS_PENDING]);
    }

    public function setFeatured(): bool
    {
        return $this->update([
            'featured' => true,
            'featured_at' => now(),
        ]);
    }

    public function removeFeatured(): bool
    {
        return $this->update([
            'featured' => false,
            'featured_at' => null,
        ]);
    }

    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    public function incrementShareCount(): void
    {
        $this->increment('share_count');
    }

    // Accessors
    public function getCoverPhotoUrlAttribute(): ?string
    {
        if ($this->cover_photo_path) {
            return asset('storage/' . $this->cover_photo_path);
        }

        // Fallback to first photo
        $firstPhoto = $this->photos()->first();
        return $firstPhoto?->file_url;
    }

    public function getIsApprovedAttribute(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function getIsDraftAttribute(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function getIsRejectedAttribute(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function getCanEditAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED]);
    }
}
