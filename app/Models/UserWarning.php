<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class UserWarning extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'warning_template_id',
        'report_id',
        'issued_by',
        'warning_type',
        'severity',
        'reason',
        'description',
        'evidence',
        'issued_at',
        'expires_at',
        'acknowledged_at',
        'acknowledged_by',
        'status',
        'appeal_status',
        'appeal_submitted_at',
        'appeal_processed_at',
        'appeal_processed_by',
        'appeal_decision',
        'appeal_notes',
        'metadata',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'evidence' => 'array',
        'metadata' => 'array',
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'appeal_submitted_at' => 'datetime',
        'appeal_processed_at' => 'datetime',
        'severity' => 'integer',
    ];

    protected $dates = [
        'issued_at',
        'expires_at',
        'acknowledged_at',
        'appeal_submitted_at',
        'appeal_processed_at',
    ];

    // Warning Types
    const TYPE_INAPPROPRIATE_CONTENT = 'inappropriate_content';
    const TYPE_HARASSMENT = 'harassment';
    const TYPE_SPAM = 'spam';
    const TYPE_FAKE_PROFILE = 'fake_profile';
    const TYPE_UNDERAGE = 'underage';
    const TYPE_VIOLENCE = 'violence';
    const TYPE_FRAUD = 'fraud';
    const TYPE_COPYRIGHT = 'copyright';
    const TYPE_TERMS_VIOLATION = 'terms_violation';
    const TYPE_OTHER = 'other';

    // Severity Levels
    const SEVERITY_LOW = 1;
    const SEVERITY_MEDIUM = 2;
    const SEVERITY_HIGH = 3;
    const SEVERITY_CRITICAL = 4;

    // Warning Status
    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_ACKNOWLEDGED = 'acknowledged';
    const STATUS_APPEALED = 'appealed';
    const STATUS_UPHELD = 'upheld';
    const STATUS_OVERTURNED = 'overturned';
    const STATUS_DISMISSED = 'dismissed';

    // Appeal Status
    const APPEAL_PENDING = 'pending';
    const APPEAL_UNDER_REVIEW = 'under_review';
    const APPEAL_APPROVED = 'approved';
    const APPEAL_REJECTED = 'rejected';

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function warningTemplate(): BelongsTo
    {
        return $this->belongsTo(WarningTemplate::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function appealProcessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'appeal_processed_by');
    }

    public function relatedWarnings(): HasMany
    {
        return $this->hasMany(UserWarning::class, 'user_id', 'user_id');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_EXPIRED);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('warning_type', $type);
    }

    public function scopeBySeverity(Builder $query, int $severity): Builder
    {
        return $query->where('severity', $severity);
    }

    public function scopeHighSeverity(Builder $query): Builder
    {
        return $query->where('severity', '>=', self::SEVERITY_HIGH);
    }

    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByIssuer(Builder $query, int $issuerId): Builder
    {
        return $query->where('issued_by', $issuerId);
    }

    public function scopeAppealed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPEALED);
    }

    public function scopePendingAppeal(Builder $query): Builder
    {
        return $query->where('appeal_status', self::APPEAL_PENDING);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('issued_at', '>=', now()->subDays($days));
    }

    // Methods
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED || 
               ($this->expires_at && $this->expires_at->isPast());
    }

    public function isAcknowledged(): bool
    {
        return $this->status === self::STATUS_ACKNOWLEDGED;
    }

    public function isAppealed(): bool
    {
        return $this->status === self::STATUS_APPEALED;
    }

    public function isUpheld(): bool
    {
        return $this->status === self::STATUS_UPHELD;
    }

    public function isOverturned(): bool
    {
        return $this->status === self::STATUS_OVERTURNED;
    }

    public function isHighSeverity(): bool
    {
        return $this->severity >= self::SEVERITY_HIGH;
    }

    public function isCritical(): bool
    {
        return $this->severity === self::SEVERITY_CRITICAL;
    }

    public function acknowledge(User $user): bool
    {
        return $this->update([
            'status' => self::STATUS_ACKNOWLEDGED,
            'acknowledged_at' => now(),
            'acknowledged_by' => $user->id,
        ]);
    }

    public function appeal(string $reason = null): bool
    {
        return $this->update([
            'status' => self::STATUS_APPEALED,
            'appeal_status' => self::APPEAL_PENDING,
            'appeal_submitted_at' => now(),
            'appeal_notes' => $reason,
        ]);
    }

    public function processAppeal(User $admin, string $decision, string $notes = null): bool
    {
        $status = $decision === 'approved' ? self::STATUS_OVERTURNED : self::STATUS_UPHELD;
        $appealStatus = $decision === 'approved' ? self::APPEAL_APPROVED : self::APPEAL_REJECTED;

        return $this->update([
            'status' => $status,
            'appeal_status' => $appealStatus,
            'appeal_decision' => $decision,
            'appeal_processed_at' => now(),
            'appeal_processed_by' => $admin->id,
            'appeal_notes' => $notes,
        ]);
    }

    public function expire(): bool
    {
        return $this->update(['status' => self::STATUS_EXPIRED]);
    }

    public function dismiss(): bool
    {
        return $this->update(['status' => self::STATUS_DISMISSED]);
    }

    public function getSeverityLabel(): string
    {
        return match($this->severity) {
            self::SEVERITY_LOW => 'Low',
            self::SEVERITY_MEDIUM => 'Medium',
            self::SEVERITY_HIGH => 'High',
            self::SEVERITY_CRITICAL => 'Critical',
            default => 'Unknown'
        };
    }

    public function getTypeLabel(): string
    {
        return match($this->warning_type) {
            self::TYPE_INAPPROPRIATE_CONTENT => 'Inappropriate Content',
            self::TYPE_HARASSMENT => 'Harassment',
            self::TYPE_SPAM => 'Spam',
            self::TYPE_FAKE_PROFILE => 'Fake Profile',
            self::TYPE_UNDERAGE => 'Underage User',
            self::TYPE_VIOLENCE => 'Violence',
            self::TYPE_FRAUD => 'Fraud',
            self::TYPE_COPYRIGHT => 'Copyright Violation',
            self::TYPE_TERMS_VIOLATION => 'Terms of Service Violation',
            self::TYPE_OTHER => 'Other',
            default => 'Unknown'
        };
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_ACKNOWLEDGED => 'Acknowledged',
            self::STATUS_APPEALED => 'Appealed',
            self::STATUS_UPHELD => 'Upheld',
            self::STATUS_OVERTURNED => 'Overturned',
            self::STATUS_DISMISSED => 'Dismissed',
            default => 'Unknown'
        };
    }

    public function getAppealStatusLabel(): string
    {
        return match($this->appeal_status) {
            self::APPEAL_PENDING => 'Pending',
            self::APPEAL_UNDER_REVIEW => 'Under Review',
            self::APPEAL_APPROVED => 'Approved',
            self::APPEAL_REJECTED => 'Rejected',
            default => 'Unknown'
        };
    }

    public function getTimeUntilExpiry(): ?string
    {
        if (!$this->expires_at) {
            return null;
        }

        if ($this->expires_at->isPast()) {
            return 'Expired';
        }

        $diff = now()->diff($this->expires_at);
        
        if ($diff->days > 0) {
            return "{$diff->days} days";
        } elseif ($diff->h > 0) {
            return "{$diff->h} hours";
        } else {
            return "{$diff->i} minutes";
        }
    }

    public function getWarningCount(): int
    {
        return $this->relatedWarnings()->count();
    }

    public function getActiveWarningCount(): int
    {
        return $this->relatedWarnings()->active()->count();
    }

    public function getHighSeverityWarningCount(): int
    {
        return $this->relatedWarnings()->highSeverity()->count();
    }

    // Static methods
    public static function issueWarning(
        User $user,
        string $type,
        int $severity,
        string $reason,
        string $description = null,
        array $evidence = [],
        User $issuedBy = null,
        ?int $reportId = null,
        ?int $templateId = null,
        ?string $expiresAt = null
    ): self {
        return self::create([
            'user_id' => $user->id,
            'warning_template_id' => $templateId,
            'report_id' => $reportId,
            'issued_by' => $issuedBy?->id,
            'warning_type' => $type,
            'severity' => $severity,
            'reason' => $reason,
            'description' => $description,
            'evidence' => $evidence,
            'issued_at' => now(),
            'expires_at' => $expiresAt ? now()->addDays($expiresAt) : null,
            'status' => self::STATUS_ACTIVE,
            'appeal_status' => null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public static function getWarningStats(User $user): array
    {
        $totalWarnings = self::where('user_id', $user->id)->count();
        $activeWarnings = self::where('user_id', $user->id)->active()->count();
        $highSeverityWarnings = self::where('user_id', $user->id)->highSeverity()->count();
        $appealedWarnings = self::where('user_id', $user->id)->appealed()->count();
        $upheldWarnings = self::where('user_id', $user->id)->where('status', self::STATUS_UPHELD)->count();
        $overturnedWarnings = self::where('user_id', $user->id)->where('status', self::STATUS_OVERTURNED)->count();

        return [
            'total_warnings' => $totalWarnings,
            'active_warnings' => $activeWarnings,
            'high_severity_warnings' => $highSeverityWarnings,
            'appealed_warnings' => $appealedWarnings,
            'upheld_warnings' => $upheldWarnings,
            'overturned_warnings' => $overturnedWarnings,
            'appeal_success_rate' => $totalWarnings > 0 ? round(($overturnedWarnings / $totalWarnings) * 100, 2) : 0,
        ];
    }

    public static function getSystemWarningStats(): array
    {
        $totalWarnings = self::count();
        $activeWarnings = self::active()->count();
        $expiredWarnings = self::expired()->count();
        $appealedWarnings = self::appealed()->count();
        $pendingAppeals = self::pendingAppeal()->count();
        $highSeverityWarnings = self::highSeverity()->count();

        return [
            'total_warnings' => $totalWarnings,
            'active_warnings' => $activeWarnings,
            'expired_warnings' => $expiredWarnings,
            'appealed_warnings' => $appealedWarnings,
            'pending_appeals' => $pendingAppeals,
            'high_severity_warnings' => $highSeverityWarnings,
            'appeal_rate' => $totalWarnings > 0 ? round(($appealedWarnings / $totalWarnings) * 100, 2) : 0,
        ];
    }
} 