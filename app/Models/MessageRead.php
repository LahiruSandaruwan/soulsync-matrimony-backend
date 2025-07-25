<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MessageRead extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'user_id',
        'read_at',
        'read_via',
        'device_info',
        'ip_address'
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'device_info' => 'array',
    ];

    protected $dates = [
        'read_at',
    ];

    // Read Via Options
    const VIA_WEB = 'web';
    const VIA_MOBILE = 'mobile';
    const VIA_PUSH = 'push';
    const VIA_EMAIL = 'email';

    // Relationships
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Methods
    public function markAsRead(string $via = self::VIA_WEB): bool
    {
        return $this->update([
            'read_at' => now(),
            'read_via' => $via,
        ]);
    }

    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }

    public function getReadViaLabel(): string
    {
        return match($this->read_via) {
            self::VIA_WEB => 'Web',
            self::VIA_MOBILE => 'Mobile App',
            self::VIA_PUSH => 'Push Notification',
            self::VIA_EMAIL => 'Email',
            default => 'Unknown'
        };
    }

    // Static methods
    public static function markMessageAsRead(Message $message, User $user, string $via = self::VIA_WEB): self
    {
        return self::updateOrCreate(
            [
                'message_id' => $message->id,
                'user_id' => $user->id,
            ],
            [
                'read_at' => now(),
                'read_via' => $via,
                'device_info' => [
                    'user_agent' => request()->userAgent(),
                    'platform' => request()->header('X-Platform'),
                    'app_version' => request()->header('X-App-Version'),
                ],
                'ip_address' => request()->ip(),
            ]
        );
    }

    public static function getUnreadMessagesCount(User $user): int
    {
        return Message::whereHas('conversation.participants', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
        ->whereDoesntHave('readBy', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
        ->where('sender_id', '!=', $user->id)
        ->count();
    }

    public static function getUnreadMessagesForConversation(Conversation $conversation, User $user): int
    {
        return $conversation->messages()
            ->whereDoesntHave('readBy', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->where('sender_id', '!=', $user->id)
            ->count();
    }
} 