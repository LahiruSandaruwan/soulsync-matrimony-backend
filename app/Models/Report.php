<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'reporter_id',
        'reported_user_id',
        'type',
        'category',
        'reason',
        'description',
        'evidence',
        'status',
        'priority',
        'assigned_admin_id',
        'resolved_at',
        'resolution_notes',
        'action_taken',
        'warning_issued',
        'user_suspended',
        'user_banned',
        'photo_removed',
        'message_removed',
        'metadata',
        'ip_address',
        'user_agent',
        'location_data'
    ];

    protected $casts = [
        'evidence' => 'array',
        'metadata' => 'array',
        'location_data' => 'array',
        'resolved_at' => 'datetime',
        'warning_issued' => 'boolean',
        'user_suspended' => 'boolean',
        'user_banned' => 'boolean',
        'photo_removed' => 'boolean',
        'message_removed' => 'boolean',
        'priority' => 'integer',
    ];

    protected $dates = [
        'resolved_at',
    ];

    // Report Types
    const TYPE_INAPPROPRIATE_CONTENT = 'inappropriate_content';
    const TYPE_HARASSMENT = 'harassment';
    const TYPE_FAKE_PROFILE = 'fake_profile';
    const TYPE_SPAM = 'spam';
    const TYPE_UNDERAGE = 'underage';
    const TYPE_VIOLENCE = 'violence';
    const TYPE_FRAUD = 'fraud';
    const TYPE_OTHER = 'other';

    // Report Categories
    const CATEGORY_PROFILE = 'profile';
    const CATEGORY_PHOTO = 'photo';
    const CATEGORY_MESSAGE = 'message';
    const CATEGORY_BEHAVIOR = 'behavior';
    const CATEGORY_PAYMENT = 'payment';
    const CATEGORY_TECHNICAL = 'technical';

    // Report Status
    const STATUS_PENDING = 'pending';
    const STATUS_UNDER_REVIEW = 'under_review';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_DISMISSED = 'dismissed';
    const STATUS_ESCALATED = 'escalated';

    // Priority Levels
    const PRIORITY_LOW = 1;
    const PRIORITY_MEDIUM = 3;
    const PRIORITY_HIGH = 5;
    const PRIORITY_URGENT = 7;
    const PRIORITY_CRITICAL = 9;

    // Relationships
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function warnings(): HasMany
    {
        return $this->hasMany(UserWarning::class);
    }

    // Scopes
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeUnderReview(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_UNDER_REVIEW);
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RESOLVED);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeByPriority(Builder $query, int $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    public function scopeHighPriority(Builder $query): Builder
    {
        return $query->where('priority', '>=', self::PRIORITY_HIGH);
    }

    public function scopeUrgent(Builder $query): Builder
    {
        return $query->where('priority', '>=', self::PRIORITY_URGENT);
    }

    public function scopeByReporter(Builder $query, int $reporterId): Builder
    {
        return $query->where('reporter_id', $reporterId);
    }

    public function scopeByReportedUser(Builder $query, int $reportedUserId): Builder
    {
        return $query->where('reported_user_id', $reportedUserId);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('assigned_admin_id');
    }

    public function scopeAssignedTo(Builder $query, int $adminId): Builder
    {
        return $query->where('assigned_admin_id', $adminId);
    }

    // Methods
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isUnderReview(): bool
    {
        return $this->status === self::STATUS_UNDER_REVIEW;
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function isDismissed(): bool
    {
        return $this->status === self::STATUS_DISMISSED;
    }

    public function isEscalated(): bool
    {
        return $this->status === self::STATUS_ESCALATED;
    }

    public function isHighPriority(): bool
    {
        return $this->priority >= self::PRIORITY_HIGH;
    }

    public function isUrgent(): bool
    {
        return $this->priority >= self::PRIORITY_URGENT;
    }

    public function isAssigned(): bool
    {
        return !is_null($this->assigned_admin_id);
    }

    public function assignTo(User $admin): bool
    {
        return $this->update([
            'assigned_admin_id' => $admin->id,
            'status' => self::STATUS_UNDER_REVIEW
        ]);
    }

    public function markAsResolved(string $notes = null, array $actions = []): bool
    {
        $updateData = [
            'status' => self::STATUS_RESOLVED,
            'resolved_at' => now(),
            'resolution_notes' => $notes
        ];

        // Apply actions taken
        if (isset($actions['warning_issued'])) {
            $updateData['warning_issued'] = $actions['warning_issued'];
        }
        if (isset($actions['user_suspended'])) {
            $updateData['user_suspended'] = $actions['user_suspended'];
        }
        if (isset($actions['user_banned'])) {
            $updateData['user_banned'] = $actions['user_banned'];
        }
        if (isset($actions['photo_removed'])) {
            $updateData['photo_removed'] = $actions['photo_removed'];
        }
        if (isset($actions['message_removed'])) {
            $updateData['message_removed'] = $actions['message_removed'];
        }

        return $this->update($updateData);
    }

    public function dismiss(string $notes = null): bool
    {
        return $this->update([
            'status' => self::STATUS_DISMISSED,
            'resolved_at' => now(),
            'resolution_notes' => $notes
        ]);
    }

    public function escalate(string $notes = null): bool
    {
        return $this->update([
            'status' => self::STATUS_ESCALATED,
            'resolution_notes' => $notes
        ]);
    }

    public function getPriorityLabel(): string
    {
        return match($this->priority) {
            self::PRIORITY_LOW => 'Low',
            self::PRIORITY_MEDIUM => 'Medium',
            self::PRIORITY_HIGH => 'High',
            self::PRIORITY_URGENT => 'Urgent',
            self::PRIORITY_CRITICAL => 'Critical',
            default => 'Unknown'
        };
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_UNDER_REVIEW => 'Under Review',
            self::STATUS_RESOLVED => 'Resolved',
            self::STATUS_DISMISSED => 'Dismissed',
            self::STATUS_ESCALATED => 'Escalated',
            default => 'Unknown'
        };
    }

    public function getTypeLabel(): string
    {
        return match($this->type) {
            self::TYPE_INAPPROPRIATE_CONTENT => 'Inappropriate Content',
            self::TYPE_HARASSMENT => 'Harassment',
            self::TYPE_FAKE_PROFILE => 'Fake Profile',
            self::TYPE_SPAM => 'Spam',
            self::TYPE_UNDERAGE => 'Underage User',
            self::TYPE_VIOLENCE => 'Violence',
            self::TYPE_FRAUD => 'Fraud',
            self::TYPE_OTHER => 'Other',
            default => 'Unknown'
        };
    }

    public function getCategoryLabel(): string
    {
        return match($this->category) {
            self::CATEGORY_PROFILE => 'Profile',
            self::CATEGORY_PHOTO => 'Photo',
            self::CATEGORY_MESSAGE => 'Message',
            self::CATEGORY_BEHAVIOR => 'Behavior',
            self::CATEGORY_PAYMENT => 'Payment',
            self::CATEGORY_TECHNICAL => 'Technical',
            default => 'Unknown'
        };
    }

    public function getTimeToResolution(): ?string
    {
        if (!$this->resolved_at) {
            return null;
        }

        $duration = $this->created_at->diffInHours($this->resolved_at);
        
        if ($duration < 24) {
            return "{$duration} hours";
        }
        
        $days = floor($duration / 24);
        $hours = $duration % 24;
        
        return $hours > 0 ? "{$days} days, {$hours} hours" : "{$days} days";
    }

    // Static methods for creating reports
    public static function createInappropriateContentReport(
        User $reporter,
        User $reportedUser,
        string $category,
        string $description,
        array $evidence = []
    ): self {
        return self::create([
            'reporter_id' => $reporter->id,
            'reported_user_id' => $reportedUser->id,
            'type' => self::TYPE_INAPPROPRIATE_CONTENT,
            'category' => $category,
            'reason' => 'Inappropriate content',
            'description' => $description,
            'evidence' => $evidence,
            'status' => self::STATUS_PENDING,
            'priority' => self::PRIORITY_HIGH,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'location_data' => [
                'country' => request()->header('CF-IPCountry'),
                'city' => request()->header('CF-IPCity')
            ]
        ]);
    }

    public static function createHarassmentReport(
        User $reporter,
        User $reportedUser,
        string $description,
        array $evidence = []
    ): self {
        return self::create([
            'reporter_id' => $reporter->id,
            'reported_user_id' => $reportedUser->id,
            'type' => self::TYPE_HARASSMENT,
            'category' => self::CATEGORY_BEHAVIOR,
            'reason' => 'Harassment',
            'description' => $description,
            'evidence' => $evidence,
            'status' => self::STATUS_PENDING,
            'priority' => self::PRIORITY_URGENT,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'location_data' => [
                'country' => request()->header('CF-IPCountry'),
                'city' => request()->header('CF-IPCity')
            ]
        ]);
    }

    public static function createFakeProfileReport(
        User $reporter,
        User $reportedUser,
        string $description,
        array $evidence = []
    ): self {
        return self::create([
            'reporter_id' => $reporter->id,
            'reported_user_id' => $reportedUser->id,
            'type' => self::TYPE_FAKE_PROFILE,
            'category' => self::CATEGORY_PROFILE,
            'reason' => 'Fake profile',
            'description' => $description,
            'evidence' => $evidence,
            'status' => self::STATUS_PENDING,
            'priority' => self::PRIORITY_HIGH,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'location_data' => [
                'country' => request()->header('CF-IPCountry'),
                'city' => request()->header('CF-IPCity')
            ]
        ]);
    }

    public static function createUnderageReport(
        User $reporter,
        User $reportedUser,
        string $description,
        array $evidence = []
    ): self {
        return self::create([
            'reporter_id' => $reporter->id,
            'reported_user_id' => $reportedUser->id,
            'type' => self::TYPE_UNDERAGE,
            'category' => self::CATEGORY_PROFILE,
            'reason' => 'Underage user',
            'description' => $description,
            'evidence' => $evidence,
            'status' => self::STATUS_PENDING,
            'priority' => self::PRIORITY_CRITICAL,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'location_data' => [
                'country' => request()->header('CF-IPCountry'),
                'city' => request()->header('CF-IPCity')
            ]
        ]);
    }
}
