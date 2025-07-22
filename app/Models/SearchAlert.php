<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'saved_search_id',
        'user_id',
        'new_results_count',
        'sample_results',
        'sent',
        'sent_at',
    ];

    protected $casts = [
        'sample_results' => 'array',
        'sent' => 'boolean',
        'sent_at' => 'datetime',
    ];

    /**
     * Get the saved search that owns this alert
     */
    public function savedSearch(): BelongsTo
    {
        return $this->belongsTo(SavedSearch::class);
    }

    /**
     * Get the user that owns this alert
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark alert as sent
     */
    public function markAsSent(): void
    {
        $this->update([
            'sent' => true,
            'sent_at' => now(),
        ]);
    }

    /**
     * Get pending alerts for a user
     */
    public static function getPendingAlertsFor(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('user_id', $user->id)
            ->where('sent', false)
            ->with('savedSearch')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Send pending alerts to users
     */
    public static function sendPendingAlerts(): int
    {
        $pendingAlerts = static::where('sent', false)
            ->with(['user', 'savedSearch'])
            ->get();

        $sentCount = 0;

        foreach ($pendingAlerts as $alert) {
            try {
                // Create notification
                $alert->user->notifications()->create([
                    'type' => 'search_alert',
                    'title' => 'New Search Results Available',
                    'content' => "Your saved search '{$alert->savedSearch->name}' has {$alert->new_results_count} new results!",
                    'data' => [
                        'saved_search_id' => $alert->saved_search_id,
                        'new_results_count' => $alert->new_results_count,
                        'sample_results' => $alert->sample_results,
                    ],
                ]);

                // Send push notification if user has push enabled
                if ($alert->user->is_premium_active) {
                    $pushService = app(\App\Services\PushNotificationService::class);
                    $pushService->sendToUser(
                        $alert->user,
                        'New Search Results',
                        "Your saved search has {$alert->new_results_count} new matches!",
                        [
                            'type' => 'search_alert',
                            'saved_search_id' => $alert->saved_search_id,
                        ]
                    );
                }

                $alert->markAsSent();
                $sentCount++;

            } catch (\Exception $e) {
                \Log::error('Failed to send search alert', [
                    'alert_id' => $alert->id,
                    'user_id' => $alert->user_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $sentCount;
    }

    /**
     * Clean up old alerts
     */
    public static function cleanup(int $daysOld = 30): int
    {
        return static::where('created_at', '<', now()->subDays($daysOld))->delete();
    }
} 