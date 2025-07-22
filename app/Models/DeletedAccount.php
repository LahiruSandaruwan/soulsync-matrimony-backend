<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeletedAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'original_user_id',
        'email',
        'first_name',
        'last_name',
        'date_of_birth',
        'gender',
        'phone',
        'country_code',
        'registration_date',
        'deletion_date',
        'deleted_by',
        'deletion_reason',
        'ip_address',
        'user_agent',
        'profile_data',
        'subscription_data',
        'total_matches',
        'total_messages',
        'total_photos',
        'total_spent',
        'data_exported',
        'data_exported_at',
        'permanent_deletion_at',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'registration_date' => 'datetime',
        'deletion_date' => 'datetime',
        'data_exported' => 'boolean',
        'data_exported_at' => 'datetime',
        'permanent_deletion_at' => 'datetime',
        'profile_data' => 'array',
        'subscription_data' => 'array',
        'total_spent' => 'decimal:2',
    ];

    /**
     * Create a deleted account record from a user
     */
    public static function createFromUser(User $user, string $deletedBy = 'user', string $reason = null): self
    {
        // Calculate user statistics
        $totalMatches = $user->matches()->whereNotNull('matched_at')->count();
        $totalMessages = $user->sentMessages()->count() + $user->receivedMessages()->count();
        $totalPhotos = $user->photos()->count();
        $totalSpent = $user->subscriptions()->sum('amount_usd');

        // Collect profile data
        $profileData = [
            'basic_info' => $user->only(['first_name', 'last_name', 'email', 'phone', 'gender', 'date_of_birth', 'country_code']),
            'profile' => $user->profile?->toArray(),
            'preferences' => $user->preferences?->toArray(),
            'horoscope' => $user->horoscope?->toArray(),
            'interests' => $user->interests?->pluck('name')->toArray(),
        ];

        // Collect subscription data
        $subscriptionData = $user->subscriptions()->get(['plan_type', 'amount_usd', 'starts_at', 'ends_at', 'status'])->toArray();

        return static::create([
            'original_user_id' => $user->id,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'date_of_birth' => $user->date_of_birth,
            'gender' => $user->gender,
            'phone' => $user->phone,
            'country_code' => $user->country_code,
            'registration_date' => $user->created_at,
            'deletion_date' => now(),
            'deleted_by' => $deletedBy,
            'deletion_reason' => $reason,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'profile_data' => $profileData,
            'subscription_data' => $subscriptionData,
            'total_matches' => $totalMatches,
            'total_messages' => $totalMessages,
            'total_photos' => $totalPhotos,
            'total_spent' => $totalSpent,
        ]);
    }

    /**
     * Mark data as exported
     */
    public function markDataExported(): void
    {
        $this->update([
            'data_exported' => true,
            'data_exported_at' => now(),
        ]);
    }

    /**
     * Schedule permanent deletion (GDPR compliance)
     */
    public function schedulePermanentDeletion(int $days = 30): void
    {
        $this->update([
            'permanent_deletion_at' => now()->addDays($days),
        ]);
    }

    /**
     * Get accounts ready for permanent deletion
     */
    public static function getReadyForPermanentDeletion(): \Illuminate\Database\Eloquent\Collection
    {
        return static::whereNotNull('permanent_deletion_at')
            ->where('permanent_deletion_at', '<=', now())
            ->get();
    }

    /**
     * Get deletion statistics
     */
    public static function getDeletionStats(int $days = 30): array
    {
        $period = now()->subDays($days);

        return [
            'total_deletions' => static::where('deletion_date', '>=', $period)->count(),
            'user_initiated' => static::where('deletion_date', '>=', $period)->where('deleted_by', 'user')->count(),
            'admin_initiated' => static::where('deletion_date', '>=', $period)->where('deleted_by', 'admin')->count(),
            'system_initiated' => static::where('deletion_date', '>=', $period)->where('deleted_by', 'system')->count(),
            'data_exported' => static::where('deletion_date', '>=', $period)->where('data_exported', true)->count(),
            'pending_permanent_deletion' => static::whereNotNull('permanent_deletion_at')
                ->where('permanent_deletion_at', '>', now())
                ->count(),
            'average_account_age' => static::where('deletion_date', '>=', $period)
                ->selectRaw('AVG(DATEDIFF(deletion_date, registration_date)) as avg_days')
                ->value('avg_days'),
        ];
    }

    /**
     * Get top deletion reasons
     */
    public static function getTopDeletionReasons(int $limit = 10): array
    {
        return static::whereNotNull('deletion_reason')
            ->selectRaw('deletion_reason, COUNT(*) as count')
            ->groupBy('deletion_reason')
            ->orderByDesc('count')
            ->limit($limit)
            ->pluck('count', 'deletion_reason')
            ->toArray();
    }

    /**
     * Clean up old deletion records
     */
    public static function cleanup(int $yearsToKeep = 7): int
    {
        return static::where('deletion_date', '<', now()->subYears($yearsToKeep))->delete();
    }

    /**
     * Export account data in various formats
     */
    public function exportData(string $format = 'json'): array
    {
        $data = [
            'account_info' => [
                'original_user_id' => $this->original_user_id,
                'email' => $this->email,
                'name' => $this->first_name . ' ' . $this->last_name,
                'registration_date' => $this->registration_date->toISOString(),
                'deletion_date' => $this->deletion_date->toISOString(),
                'deleted_by' => $this->deleted_by,
                'deletion_reason' => $this->deletion_reason,
            ],
            'statistics' => [
                'total_matches' => $this->total_matches,
                'total_messages' => $this->total_messages,
                'total_photos' => $this->total_photos,
                'total_spent' => $this->total_spent,
                'account_duration_days' => $this->registration_date->diffInDays($this->deletion_date),
            ],
            'profile_data' => $this->profile_data,
            'subscription_data' => $this->subscription_data,
        ];

        $this->markDataExported();

        return $data;
    }
} 