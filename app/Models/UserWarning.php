<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWarning extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'issued_by',
        'report_id',
        'severity',
        'category',
        'title',
        'reason',
        'evidence',
        'restrictions',
        'acknowledged',
        'acknowledged_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'restrictions' => 'array',
        'acknowledged' => 'boolean',
        'acknowledged_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user who received the warning
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the admin who issued the warning
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Get the related report if any
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * Issue a warning to a user
     */
    public static function issueWarning(
        User $user,
        User $admin,
        string $category,
        string $severity,
        string $title,
        string $reason,
        array $restrictions = [],
        ?Report $report = null,
        ?string $evidence = null
    ): self {
        // Get warning template for escalation logic
        $template = WarningTemplate::where('category', $category)
            ->where('severity', $severity)
            ->first();

        $warning = static::create([
            'user_id' => $user->id,
            'issued_by' => $admin->id,
            'report_id' => $report?->id,
            'severity' => $severity,
            'category' => $category,
            'title' => $title,
            'reason' => $reason,
            'evidence' => $evidence,
            'restrictions' => $restrictions,
            'expires_at' => static::calculateExpiryDate($severity),
        ]);

        // Update user warning statistics
        $user->increment('warning_count');
        $user->increment('warning_points', static::getPointsForSeverity($severity));
        $user->update(['last_warning_at' => now()]);

        // Apply restrictions if any
        if (!empty($restrictions)) {
            static::applyRestrictions($user, $restrictions, $warning->expires_at);
        }

        // Send notification to user
        $user->notifications()->create([
            'type' => 'account_warning',
            'title' => 'Account Warning Issued',
            'content' => "You have received a {$severity} warning: {$title}",
            'data' => [
                'warning_id' => $warning->id,
                'severity' => $severity,
                'category' => $category,
                'restrictions' => $restrictions,
            ],
        ]);

        // Check for escalation
        if ($template) {
            static::checkEscalation($user, $template);
        }

        return $warning;
    }

    /**
     * Calculate expiry date based on severity
     */
    private static function calculateExpiryDate(string $severity): ?\Carbon\Carbon
    {
        return match ($severity) {
            'minor' => now()->addDays(7),
            'moderate' => now()->addDays(14),
            'major' => now()->addDays(30),
            'severe' => now()->addDays(90),
            default => now()->addDays(14),
        };
    }

    /**
     * Get warning points for severity
     */
    private static function getPointsForSeverity(string $severity): int
    {
        return match ($severity) {
            'minor' => 1,
            'moderate' => 3,
            'major' => 5,
            'severe' => 10,
            default => 1,
        };
    }

    /**
     * Apply restrictions to user
     */
    private static function applyRestrictions(User $user, array $restrictions, ?\Carbon\Carbon $expiresAt): void
    {
        foreach ($restrictions as $restriction) {
            switch ($restriction) {
                case 'messaging_disabled':
                    // Disable messaging
                    break;
                case 'profile_hidden':
                    // Hide profile from search
                    break;
                case 'photo_upload_disabled':
                    // Disable photo uploads
                    break;
                case 'matching_disabled':
                    // Disable matching
                    break;
            }
        }

        $user->update(['restricted_until' => $expiresAt]);
    }

    /**
     * Check for escalation based on warning count
     */
    private static function checkEscalation(User $user, WarningTemplate $template): void
    {
        $recentWarnings = static::where('user_id', $user->id)
            ->where('category', $template->category)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        if ($recentWarnings >= $template->escalation_after_count) {
            switch ($template->escalation_action) {
                case 'suspend':
                    $user->update([
                        'status' => 'suspended',
                        'restricted_until' => now()->addDays(7),
                    ]);
                    break;
                case 'ban':
                    $user->update([
                        'status' => 'banned',
                        'restricted_until' => now()->addDays(30),
                    ]);
                    break;
                case 'review':
                    // Flag for manual review
                    break;
            }

            // Notify admins of escalation
            $adminUsers = User::role('admin')->get();
            foreach ($adminUsers as $admin) {
                $admin->notifications()->create([
                    'type' => 'warning_escalation',
                    'title' => 'User Warning Escalation',
                    'content' => "User {$user->first_name} {$user->last_name} has been escalated due to repeated {$template->category} violations.",
                    'data' => [
                        'user_id' => $user->id,
                        'escalation_action' => $template->escalation_action,
                        'warning_count' => $recentWarnings,
                    ],
                ]);
            }
        }
    }

    /**
     * Acknowledge a warning
     */
    public function acknowledge(): void
    {
        $this->update([
            'acknowledged' => true,
            'acknowledged_at' => now(),
        ]);
    }

    /**
     * Get active warnings for a user
     */
    public static function getActiveWarningsFor(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('user_id', $user->id)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get warning statistics for admin dashboard
     */
    public static function getStatistics(int $days = 30): array
    {
        $startDate = now()->subDays($days);
        $warnings = static::where('created_at', '>=', $startDate);

        return [
            'total_warnings' => $warnings->count(),
            'warnings_by_severity' => $warnings->groupBy('severity')->map->count(),
            'warnings_by_category' => $warnings->groupBy('category')->map->count(),
            'acknowledged_warnings' => $warnings->where('acknowledged', true)->count(),
            'active_warnings' => static::where('is_active', true)->count(),
            'users_with_warnings' => $warnings->distinct('user_id')->count(),
            'repeat_offenders' => static::getUsersWithMultipleWarnings(),
        ];
    }

    /**
     * Get users with multiple warnings
     */
    private static function getUsersWithMultipleWarnings(int $threshold = 3): \Illuminate\Database\Eloquent\Collection
    {
        return User::whereHas('warnings', function ($query) use ($threshold) {
            $query->havingRaw('COUNT(*) >= ?', [$threshold]);
        })->withCount('warnings')->get();
    }

    /**
     * Expire old warnings
     */
    public static function expireOldWarnings(): int
    {
        return static::where('is_active', true)
            ->where('expires_at', '<', now())
            ->update(['is_active' => false]);
    }
} 