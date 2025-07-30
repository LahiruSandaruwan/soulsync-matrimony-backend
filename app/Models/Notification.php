<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'actor_id',
        'type',
        'title',
        'message',
        'data',
        'read_at',
        'sent_at',
        'delivered_at',
        'priority',
        'category',
        'action_url',
        'action_text',
        'expires_at',
        'batch_id',
        'source_type',
        'source_id',
        'metadata'
    ];

    protected $casts = [
        'data' => 'array',
        'metadata' => 'array',
        'read_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $dates = [
        'read_at',
        'sent_at',
        'delivered_at',
        'expires_at',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source()
    {
        return $this->morphTo();
    }

    // Scopes
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeRead(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
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
        return $query->where('priority', '>=', 8);
    }

    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Methods
    public function markAsRead(): bool
    {
        return $this->update(['read_at' => now()]);
    }

    public function markAsUnread(): bool
    {
        return $this->update(['read_at' => null]);
    }

    public function markAsSent(): bool
    {
        return $this->update(['sent_at' => now()]);
    }

    public function markAsDelivered(): bool
    {
        return $this->update(['delivered_at' => now()]);
    }

    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }

    public function isUnread(): bool
    {
        return is_null($this->read_at);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isHighPriority(): bool
    {
        return $this->priority >= 8;
    }

    public function getFormattedMessage(): string
    {
        $message = $this->message;
        
        // Replace placeholders with actual data
        if ($this->data) {
            foreach ($this->data as $key => $value) {
                $message = str_replace("{{$key}}", $value, $message);
            }
        }
        
        return $message;
    }

    public function getActionUrl(): ?string
    {
        if (!$this->action_url) {
            return null;
        }

        // Add user-specific parameters
        $url = $this->action_url;
        $url .= (str_contains($url, '?') ? '&' : '?') . 'notification_id=' . $this->id;
        
        return $url;
    }

    // Static methods for creating notifications
    public static function createMatchNotification(User $user, User $matchedUser): self
    {
        return self::create([
            'user_id' => $user->id,
            'type' => 'match',
            'title' => 'New Match!',
            'message' => "You have a new match with {$matchedUser->first_name}!",
            'data' => [
                'matched_user_id' => $matchedUser->id,
                'matched_user_name' => $matchedUser->first_name,
                'matched_user_photo' => $matchedUser->profilePicture?->file_path
            ],
            'priority' => 'high',
            'category' => 'matching',
            'action_url' => "/matches/{$matchedUser->id}",
            'action_text' => 'View Profile',
            'source_type' => User::class,
            'source_id' => $matchedUser->id
        ]);
    }

    public static function createMessageNotification(User $user, Message $message): self
    {
        return self::create([
            'user_id' => $user->id,
            'type' => 'message',
            'title' => 'New Message',
            'message' => "You received a message from {$message->sender->first_name}",
            'data' => [
                'message_id' => $message->id,
                'sender_id' => $message->sender_id,
                'sender_name' => $message->sender->first_name,
                'conversation_id' => $message->conversation_id,
                'preview' => substr($message->content, 0, 100)
            ],
            'priority' => 'medium',
            'category' => 'message',
            'action_url' => "/chat/{$message->conversation_id}",
            'action_text' => 'Reply',
            'source_type' => Message::class,
            'source_id' => $message->id
        ]);
    }

    public static function createProfileViewNotification(User $user, User $viewer): self
    {
        return self::create([
            'user_id' => $user->id,
            'type' => 'profile_view',
            'title' => 'Profile Viewed',
            'message' => "{$viewer->first_name} viewed your profile",
            'data' => [
                'viewer_id' => $viewer->id,
                'viewer_name' => $viewer->first_name,
                'viewer_photo' => $viewer->profilePicture?->file_path
            ],
            'priority' => 'low',
            'category' => 'profile',
            'action_url' => "/users/{$viewer->id}",
            'action_text' => 'View Profile',
            'source_type' => User::class,
            'source_id' => $viewer->id
        ]);
    }

    public static function createSubscriptionNotification(User $user, string $type, array $data = []): self
    {
        $notifications = [
            'trial_started' => [
                'title' => 'Free Trial Started',
                'message' => 'Your free trial has started! Enjoy premium features.',
                'priority' => 'medium'
            ],
            'trial_ending' => [
                'title' => 'Trial Ending Soon',
                'message' => 'Your free trial ends in 3 days. Upgrade to continue.',
                'priority' => 'high'
            ],
            'subscription_active' => [
                'title' => 'Subscription Active',
                'message' => 'Your subscription is now active!',
                'priority' => 'medium'
            ],
            'subscription_expired' => [
                'title' => 'Subscription Expired',
                'message' => 'Your subscription has expired. Renew to continue.',
                'priority' => 'high'
            ],
            'payment_failed' => [
                'title' => 'Payment Failed',
                'message' => 'Your payment failed. Please update your payment method.',
                'priority' => 'urgent'
            ]
        ];

        $notification = $notifications[$type] ?? [
            'title' => 'Subscription Update',
            'message' => 'Your subscription has been updated.',
            'priority' => 'medium'
        ];

        return self::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $notification['title'],
            'message' => $notification['message'],
            'data' => $data,
            'priority' => $notification['priority'],
            'category' => 'subscription',
            'action_url' => '/subscription',
            'action_text' => 'Manage Subscription'
        ]);
    }
}
