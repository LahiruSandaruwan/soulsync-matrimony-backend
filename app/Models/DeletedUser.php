<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeletedUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'original_user_id',
        'email',
        'first_name',
        'last_name',
        'user_data',
        'deletion_date',
        'deleted_by',
        'deleted_by_admin_id',
        'admin_reason',
        'admin_notes',
        'banned',
        'ban_expires_at',
        'can_reactivate',
        'reactivation_deadline',
    ];

    protected $casts = [
        'user_data' => 'array',
        'deletion_date' => 'datetime',
        'ban_expires_at' => 'datetime',
        'banned' => 'boolean',
        'can_reactivate' => 'boolean',
        'reactivation_deadline' => 'datetime',
    ];

    /**
     * Get the admin who deleted this user
     */
    public function deletedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_admin_id');
    }

    /**
     * Get deletion statistics for admins
     */
    public static function getDeletionStats(int $days = 30): array
    {
        $period = now()->subDays($days);

        return [
            'total_admin_deletions' => static::where('deletion_date', '>=', $period)->count(),
            'banned_users' => static::where('deletion_date', '>=', $period)->where('banned', true)->count(),
            'reactivatable_users' => static::where('deletion_date', '>=', $period)
                ->where('can_reactivate', true)
                ->where('reactivation_deadline', '>', now())
                ->count(),
            'permanent_deletions' => static::where('deletion_date', '>=', $period)
                ->where('can_reactivate', false)
                ->count(),
        ];
    }

    /**
     * Get users eligible for reactivation
     */
    public static function getReactivatableUsers(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('can_reactivate', true)
            ->where('reactivation_deadline', '>', now())
            ->orderBy('deletion_date', 'desc')
            ->get();
    }

    /**
     * Check if user can be reactivated
     */
    public function canBeReactivated(): bool
    {
        return $this->can_reactivate && 
               $this->reactivation_deadline && 
               $this->reactivation_deadline->isFuture();
    }

    /**
     * Mark for permanent deletion
     */
    public function markForPermanentDeletion(): void
    {
        $this->update([
            'can_reactivate' => false,
            'reactivation_deadline' => null,
        ]);
    }
} 