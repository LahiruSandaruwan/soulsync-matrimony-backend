<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

class PasswordChange extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'old_password_hash',
        'new_password_hash',
        'changed_by',
        'ip_address',
        'user_agent',
        'reason',
        'forced',
        'expires_at',
    ];

    protected $casts = [
        'forced' => 'boolean',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'old_password_hash',
        'new_password_hash',
    ];

    /**
     * Get the user that owns the password change
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record a password change
     */
    public static function recordChange(
        User $user,
        string $oldPassword = null,
        string $newPassword,
        string $changedBy = 'user',
        string $reason = null,
        bool $forced = false
    ): self {
        $change = static::create([
            'user_id' => $user->id,
            'old_password_hash' => $oldPassword ? Hash::make($oldPassword) : null,
            'new_password_hash' => Hash::make($newPassword),
            'changed_by' => $changedBy,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'reason' => $reason,
            'forced' => $forced,
            'expires_at' => $forced ? now()->addDays(30) : null, // Temporary passwords expire in 30 days
        ]);

        // Update user's last password change
        $user->update([
            'last_password_change' => now(),
            'password_expired' => false,
            'password_expires_at' => $forced ? now()->addDays(90) : null, // Force change every 90 days if needed
        ]);

        return $change;
    }

    /**
     * Get password change history for a user
     */
    public static function getHistoryFor(User $user, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get(['id', 'changed_by', 'reason', 'forced', 'ip_address', 'created_at']);
    }

    /**
     * Check if password was recently used
     */
    public static function wasPasswordRecentlyUsed(User $user, string $password, int $months = 6): bool
    {
        $recentChanges = static::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subMonths($months))
            ->get(['new_password_hash']);

        foreach ($recentChanges as $change) {
            if (Hash::check($password, $change->new_password_hash)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user changes password frequently (potential security issue)
     */
    public static function hasFrequentChanges(User $user, int $days = 30, int $threshold = 5): bool
    {
        $recentChanges = static::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->count();

        return $recentChanges >= $threshold;
    }

    /**
     * Get password strength score based on history
     */
    public static function getPasswordStrengthScore(User $user): int
    {
        $score = 0;
        $lastChange = static::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$lastChange) {
            return 0; // No password changes recorded
        }

        // Age factor (newer is better)
        $daysSinceChange = $lastChange->created_at->diffInDays(now());
        if ($daysSinceChange <= 30) {
            $score += 30;
        } elseif ($daysSinceChange <= 90) {
            $score += 20;
        } elseif ($daysSinceChange <= 180) {
            $score += 10;
        }

        // Frequency factor (not too frequent, not too rare)
        $totalChanges = static::where('user_id', $user->id)->count();
        $accountAge = $user->created_at->diffInMonths(now());
        
        if ($accountAge > 0) {
            $changesPerMonth = $totalChanges / $accountAge;
            if ($changesPerMonth >= 0.25 && $changesPerMonth <= 1) {
                $score += 20; // Good frequency
            } elseif ($changesPerMonth > 1) {
                $score += 10; // Too frequent
            }
        }

        // Forced changes (security concern)
        $forcedChanges = static::where('user_id', $user->id)
            ->where('forced', true)
            ->count();
        
        if ($forcedChanges == 0) {
            $score += 20; // No forced changes is good
        } elseif ($forcedChanges <= 2) {
            $score += 10; // Few forced changes
        }

        // Multiple device/location changes (potential security issue)
        $uniqueIPs = static::where('user_id', $user->id)
            ->distinct('ip_address')
            ->count('ip_address');
        
        if ($uniqueIPs <= 3) {
            $score += 20; // Consistent location
        } elseif ($uniqueIPs <= 5) {
            $score += 10; // Reasonable variation
        }

        return min($score, 100); // Cap at 100
    }

    /**
     * Clean up old password change records
     */
    public static function cleanupOld(int $monthsToKeep = 12): int
    {
        return static::where('created_at', '<', now()->subMonths($monthsToKeep))->delete();
    }

    /**
     * Get suspicious password changes
     */
    public static function getSuspiciousChanges(int $days = 7): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('created_at', '>=', now()->subDays($days))
            ->where(function ($query) {
                $query->where('forced', true)
                    ->orWhereHas('user', function ($q) {
                        // Users with multiple recent changes
                        $q->whereHas('passwordChanges', function ($subq) {
                            $subq->where('created_at', '>=', now()->subDays(7))
                                ->havingRaw('COUNT(*) >= 3');
                        });
                    });
            })
            ->with('user:id,first_name,last_name,email')
            ->get();
    }
} 