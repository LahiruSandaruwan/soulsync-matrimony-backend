<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class WarningTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'warning_type',
        'severity',
        'title',
        'message',
        'reason_template',
        'description_template',
        'restrictions',
        'duration_days',
        'is_active',
        'is_default',
        'sort_order',
        'metadata',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'restrictions' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
        'duration_days' => 'integer',
        'severity' => 'integer',
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

    // Restrictions
    const RESTRICTION_MESSAGING_DISABLED = 'messaging_disabled';
    const RESTRICTION_PROFILE_HIDDEN = 'profile_hidden';
    const RESTRICTION_PHOTO_UPLOAD_DISABLED = 'photo_upload_disabled';
    const RESTRICTION_MATCHING_DISABLED = 'matching_disabled';
    const RESTRICTION_COMMENTING_DISABLED = 'commenting_disabled';
    const RESTRICTION_LIKING_DISABLED = 'liking_disabled';
    const RESTRICTION_SUPER_LIKING_DISABLED = 'super_liking_disabled';

    // Relationships
    public function warnings(): HasMany
    {
        return $this->hasMany(UserWarning::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
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

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc')
                    ->orderBy('name', 'asc');
    }

    // Methods
    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isDefault(): bool
    {
        return $this->is_default;
    }

    public function isHighSeverity(): bool
    {
        return $this->severity >= self::SEVERITY_HIGH;
    }

    public function isCritical(): bool
    {
        return $this->severity === self::SEVERITY_CRITICAL;
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

    public function getRestrictions(): array
    {
        return $this->restrictions ?? [];
    }

    public function hasRestriction(string $restriction): bool
    {
        return in_array($restriction, $this->getRestrictions());
    }

    public function getDurationDays(): int
    {
        return $this->duration_days ?? 0;
    }

    public function getExpiryDate(): ?\Carbon\Carbon
    {
        if ($this->duration_days <= 0) {
            return null;
        }

        return now()->addDays($this->duration_days);
    }

    public function formatMessage(array $variables = []): string
    {
        $message = $this->message;
        
        foreach ($variables as $key => $value) {
            $message = str_replace("{{$key}}", $value, $message);
        }
        
        return $message;
    }

    public function formatReason(array $variables = []): string
    {
        $reason = $this->reason_template;
        
        foreach ($variables as $key => $value) {
            $reason = str_replace("{{$key}}", $value, $reason);
        }
        
        return $reason;
    }

    public function formatDescription(array $variables = []): string
    {
        $description = $this->description_template;
        
        foreach ($variables as $key => $value) {
            $description = str_replace("{{$key}}", $value, $description);
        }
        
        return $description;
    }

    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    public function setAsDefault(): bool
    {
        // Remove default from other templates of same type and severity
        self::where('warning_type', $this->warning_type)
            ->where('severity', $this->severity)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        return $this->update(['is_default' => true]);
    }

    public function updateSortOrder(int $order): bool
    {
        return $this->update(['sort_order' => $order]);
    }

    public function getUsageCount(): int
    {
        return $this->warnings()->count();
    }

    public function getRecentUsageCount(int $days = 30): int
    {
        return $this->warnings()
            ->where('issued_at', '>=', now()->subDays($days))
            ->count();
    }

    public function getMetadata(string $key = null)
    {
        if ($key) {
            return $this->metadata[$key] ?? null;
        }

        return $this->metadata;
    }

    public function setMetadata(string $key, $value): bool
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;

        return $this->update(['metadata' => $metadata]);
    }

    // Static methods
    public static function getDefaultTemplate(string $type, int $severity): ?self
    {
        return self::where('warning_type', $type)
                  ->where('severity', $severity)
                  ->where('is_default', true)
                  ->where('is_active', true)
                  ->first();
    }

    public static function getTemplatesByType(string $type): array
    {
        return self::where('warning_type', $type)
                  ->where('is_active', true)
                  ->ordered()
                  ->get()
                  ->toArray();
    }

    public static function getTemplatesBySeverity(int $severity): array
    {
        return self::where('severity', $severity)
                  ->where('is_active', true)
                  ->ordered()
                  ->get()
                  ->toArray();
    }

    public static function getActiveTemplates(): array
    {
        return self::active()->ordered()->get()->toArray();
    }

    public static function getDefaultTemplates(): array
    {
        return self::default()->active()->ordered()->get()->toArray();
    }

    public static function createTemplate(
        string $name,
        string $type,
        int $severity,
        string $title,
        string $message,
        string $reasonTemplate = null,
        string $descriptionTemplate = null,
        array $restrictions = [],
        int $durationDays = 0,
        bool $isActive = true,
        bool $isDefault = false,
        int $sortOrder = 0,
        array $metadata = []
    ): self {
        return self::create([
            'name' => $name,
            'description' => $name,
            'warning_type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'reason_template' => $reasonTemplate,
            'description_template' => $descriptionTemplate,
            'restrictions' => $restrictions,
            'duration_days' => $durationDays,
            'is_active' => $isActive,
            'is_default' => $isDefault,
            'sort_order' => $sortOrder,
            'metadata' => $metadata,
        ]);
    }

    public static function getAvailableRestrictions(): array
    {
        return [
            self::RESTRICTION_MESSAGING_DISABLED => 'Disable Messaging',
            self::RESTRICTION_PROFILE_HIDDEN => 'Hide Profile',
            self::RESTRICTION_PHOTO_UPLOAD_DISABLED => 'Disable Photo Upload',
            self::RESTRICTION_MATCHING_DISABLED => 'Disable Matching',
            self::RESTRICTION_COMMENTING_DISABLED => 'Disable Commenting',
            self::RESTRICTION_LIKING_DISABLED => 'Disable Liking',
            self::RESTRICTION_SUPER_LIKING_DISABLED => 'Disable Super Liking',
        ];
    }

    public static function getTemplateStats(): array
    {
        $totalTemplates = self::count();
        $activeTemplates = self::active()->count();
        $defaultTemplates = self::default()->count();
        $highSeverityTemplates = self::highSeverity()->count();

        $usageByType = self::withCount('warnings')
            ->get()
            ->groupBy('warning_type')
            ->map(function ($templates) {
                return $templates->sum('warnings_count');
            });

        return [
            'total_templates' => $totalTemplates,
            'active_templates' => $activeTemplates,
            'default_templates' => $defaultTemplates,
            'high_severity_templates' => $highSeverityTemplates,
            'usage_by_type' => $usageByType,
            'activation_rate' => $totalTemplates > 0 ? round(($activeTemplates / $totalTemplates) * 100, 2) : 0,
        ];
    }

    public static function getDefaultTemplatesData(): array
    {
        return [
            [
                'name' => 'Minor Inappropriate Content',
                'type' => self::TYPE_INAPPROPRIATE_CONTENT,
                'severity' => self::SEVERITY_LOW,
                'title' => 'Content Guidelines Violation',
                'message' => 'Your content has been flagged for violating our community guidelines. Please review our content policies.',
                'reason_template' => 'Content violates community guidelines: {specific_violation}',
                'duration_days' => 7,
                'restrictions' => [self::RESTRICTION_COMMENTING_DISABLED],
            ],
            [
                'name' => 'Moderate Harassment',
                'type' => self::TYPE_HARASSMENT,
                'severity' => self::SEVERITY_MEDIUM,
                'title' => 'Harassment Warning',
                'message' => 'You have been reported for harassing behavior. This is a serious violation of our terms of service.',
                'reason_template' => 'Harassment reported: {harassment_type}',
                'duration_days' => 14,
                'restrictions' => [self::RESTRICTION_MESSAGING_DISABLED, self::RESTRICTION_LIKING_DISABLED],
            ],
            [
                'name' => 'Major Fake Profile',
                'type' => self::TYPE_FAKE_PROFILE,
                'severity' => self::SEVERITY_HIGH,
                'title' => 'Fake Profile Warning',
                'message' => 'Your profile has been flagged as potentially fake. Please provide verification or your account may be suspended.',
                'reason_template' => 'Fake profile detected: {evidence}',
                'duration_days' => 30,
                'restrictions' => [self::RESTRICTION_PROFILE_HIDDEN, self::RESTRICTION_MATCHING_DISABLED],
            ],
            [
                'name' => 'Critical Underage User',
                'type' => self::TYPE_UNDERAGE,
                'severity' => self::SEVERITY_CRITICAL,
                'title' => 'Account Suspension - Underage User',
                'message' => 'Your account has been suspended due to being underage. You must be 18 or older to use this service.',
                'reason_template' => 'User reported as underage: {age_evidence}',
                'duration_days' => 90,
                'restrictions' => [self::RESTRICTION_PROFILE_HIDDEN, self::RESTRICTION_MESSAGING_DISABLED, self::RESTRICTION_MATCHING_DISABLED],
            ],
        ];
    }
} 