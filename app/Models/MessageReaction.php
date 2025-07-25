<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MessageReaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'user_id',
        'reaction',
        'emoji',
        'created_at',
        'updated_at'
    ];

    // Reaction Types
    const REACTION_LIKE = 'like';
    const REACTION_LOVE = 'love';
    const REACTION_LAUGH = 'laugh';
    const REACTION_WOW = 'wow';
    const REACTION_SAD = 'sad';
    const REACTION_ANGRY = 'angry';
    const REACTION_HEART = 'heart';
    const REACTION_THUMBS_UP = 'thumbs_up';
    const REACTION_THUMBS_DOWN = 'thumbs_down';
    const REACTION_CLAP = 'clap';
    const REACTION_FIRE = 'fire';
    const REACTION_PRAY = 'pray';

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
    public function getEmoji(): string
    {
        return match($this->reaction) {
            self::REACTION_LIKE => '👍',
            self::REACTION_LOVE => '❤️',
            self::REACTION_LAUGH => '😂',
            self::REACTION_WOW => '😮',
            self::REACTION_SAD => '😢',
            self::REACTION_ANGRY => '😠',
            self::REACTION_HEART => '💖',
            self::REACTION_THUMBS_UP => '👍',
            self::REACTION_THUMBS_DOWN => '👎',
            self::REACTION_CLAP => '👏',
            self::REACTION_FIRE => '🔥',
            self::REACTION_PRAY => '🙏',
            default => '👍'
        };
    }

    public function getReactionLabel(): string
    {
        return match($this->reaction) {
            self::REACTION_LIKE => 'Like',
            self::REACTION_LOVE => 'Love',
            self::REACTION_LAUGH => 'Laugh',
            self::REACTION_WOW => 'Wow',
            self::REACTION_SAD => 'Sad',
            self::REACTION_ANGRY => 'Angry',
            self::REACTION_HEART => 'Heart',
            self::REACTION_THUMBS_UP => 'Thumbs Up',
            self::REACTION_THUMBS_DOWN => 'Thumbs Down',
            self::REACTION_CLAP => 'Clap',
            self::REACTION_FIRE => 'Fire',
            self::REACTION_PRAY => 'Pray',
            default => 'Like'
        };
    }

    // Static methods
    public static function addReaction(Message $message, User $user, string $reaction): self
    {
        return self::updateOrCreate(
            [
                'message_id' => $message->id,
                'user_id' => $user->id,
            ],
            [
                'reaction' => $reaction,
                'emoji' => (new self())->getEmoji(),
            ]
        );
    }

    public static function removeReaction(Message $message, User $user): bool
    {
        return self::where('message_id', $message->id)
                  ->where('user_id', $user->id)
                  ->delete();
    }

    public static function getReactionCount(Message $message, string $reaction = null): int
    {
        $query = self::where('message_id', $message->id);
        
        if ($reaction) {
            $query->where('reaction', $reaction);
        }
        
        return $query->count();
    }

    public static function getReactionsByType(Message $message): array
    {
        return self::where('message_id', $message->id)
                  ->selectRaw('reaction, emoji, COUNT(*) as count')
                  ->groupBy('reaction', 'emoji')
                  ->orderBy('count', 'desc')
                  ->get()
                  ->toArray();
    }

    public static function getUserReaction(Message $message, User $user): ?string
    {
        $reaction = self::where('message_id', $message->id)
                       ->where('user_id', $user->id)
                       ->first();
        
        return $reaction ? $reaction->reaction : null;
    }

    public static function getAvailableReactions(): array
    {
        return [
            self::REACTION_LIKE => ['emoji' => '👍', 'label' => 'Like'],
            self::REACTION_LOVE => ['emoji' => '❤️', 'label' => 'Love'],
            self::REACTION_LAUGH => ['emoji' => '😂', 'label' => 'Laugh'],
            self::REACTION_WOW => ['emoji' => '😮', 'label' => 'Wow'],
            self::REACTION_SAD => ['emoji' => '😢', 'label' => 'Sad'],
            self::REACTION_ANGRY => ['emoji' => '😠', 'label' => 'Angry'],
            self::REACTION_HEART => ['emoji' => '💖', 'label' => 'Heart'],
            self::REACTION_THUMBS_UP => ['emoji' => '👍', 'label' => 'Thumbs Up'],
            self::REACTION_THUMBS_DOWN => ['emoji' => '👎', 'label' => 'Thumbs Down'],
            self::REACTION_CLAP => ['emoji' => '👏', 'label' => 'Clap'],
            self::REACTION_FIRE => ['emoji' => '🔥', 'label' => 'Fire'],
            self::REACTION_PRAY => ['emoji' => '🙏', 'label' => 'Pray'],
        ];
    }
} 