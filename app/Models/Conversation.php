<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_one_id',
        'user_two_id',
        'match_id',
        'name',
        'type',
        'is_group',
        'status',
        'last_message',
        'last_message_type',
        'last_message_by',
        'last_message_at',
        'user_one_read_at',
        'user_two_read_at',
        'user_one_unread_count',
        'user_two_unread_count',
        'blocked_by',
        'blocked_at',
        'block_reason',
        'is_premium_conversation',
        'priority_conversation',
        'conversation_settings',
        'total_messages',
        'started_at',
        'days_active',
        'metadata',
        'created_by',
        'updated_at',
        'created_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'conversation_settings' => 'array',
        'is_group' => 'boolean',
        'is_premium_conversation' => 'boolean',
        'priority_conversation' => 'boolean',
        'user_one_unread_count' => 'integer',
        'user_two_unread_count' => 'integer',
        'total_messages' => 'integer',
        'days_active' => 'integer',
        'blocked_at' => 'datetime',
        'last_message_at' => 'datetime',
        'started_at' => 'datetime',
        'user_one_read_at' => 'datetime',
        'user_two_read_at' => 'datetime',
    ];



    // Conversation Types
    const TYPE_MATCH = 'match';
    const TYPE_INTEREST = 'interest';
    const TYPE_PREMIUM = 'premium';

    // Conversation Status
    const STATUS_ACTIVE = 'active';
    const STATUS_ARCHIVED = 'archived';
    const STATUS_BLOCKED = 'blocked';
    const STATUS_DELETED = 'deleted';

    // Relationships
    public function participants()
    {
        return collect([$this->userOne, $this->userTwo])->filter();
    }

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latest();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function blockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'source');
    }

    public function match(): HasOne
    {
        return $this->hasOne(UserMatch::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ARCHIVED);
    }

    public function scopeBlocked(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_BLOCKED);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeDirect(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_MATCH)->where('is_group', false);
    }

    public function scopeGroup(Builder $query): Builder
    {
        return $query->where('is_group', true);
    }

    public function scopeMatch(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_MATCH);
    }

    public function scopeSupport(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_MATCH);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where(function ($q) use ($user) {
            $q->where('user_one_id', $user->id)
              ->orWhere('user_two_id', $user->id);
        });
    }

    public function scopeWithUnreadMessages(Builder $query, User $user): Builder
    {
        return $query->where(function ($q) use ($user) {
            $q->where(function ($subQ) use ($user) {
                $subQ->where('user_one_id', $user->id)
                     ->where('user_one_unread_count', '>', 0);
            })->orWhere(function ($subQ) use ($user) {
                $subQ->where('user_two_id', $user->id)
                     ->where('user_two_unread_count', '>', 0);
            });
        });
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('last_message_at', '>=', now()->subDays($days));
    }

    public function scopeOrderByLastMessage(Builder $query): Builder
    {
        return $query->orderBy('last_message_at', 'desc');
    }

    public function scopeOrderByUnreadCount(Builder $query): Builder
    {
        return $query->orderBy('user_one_unread_count', 'desc');
    }

    // Methods
    public function isDirect(): bool
    {
        return $this->type === self::TYPE_MATCH && !$this->is_group;
    }

    public function isGroup(): bool
    {
        return $this->is_group;
    }

    public function isMatch(): bool
    {
        return $this->type === self::TYPE_MATCH;
    }

    public function isSupport(): bool
    {
        return $this->type === self::TYPE_MATCH;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function isBlocked(): bool
    {
        return $this->status === self::STATUS_BLOCKED;
    }

    public function isDeleted(): bool
    {
        return $this->status === self::STATUS_DELETED;
    }

    public function hasUnreadMessages(User $user): bool
    {
        $participant = $this->participants()->where('user_id', $user->id)->first();
        return $participant && $participant->pivot->unread_count > 0;
    }

    public function getUnreadCount(User $user): int
    {
        if ($this->user_one_id === $user->id) {
            return $this->user_one_unread_count;
        } elseif ($this->user_two_id === $user->id) {
            return $this->user_two_unread_count;
        }
        return 0;
    }

    public function getOtherParticipant(User $user): ?User
    {
        if ($this->user_one_id === $user->id) {
            return $this->userTwo;
        } elseif ($this->user_two_id === $user->id) {
            return $this->userOne;
        }
        return null;
    }

    public function getDisplayName(User $user): string
    {
        if ($this->name) {
            return $this->name;
        }

        $otherUser = $this->getOtherParticipant($user);
        return $otherUser ? $otherUser->first_name : 'Unknown User';
    }

    public function getLastMessagePreview(): ?string
    {
        if (!$this->lastMessage) {
            return null;
        }

        $message = $this->lastMessage;
        
        if ($message->is_deleted) {
            return '[Message deleted]';
        }

        if ($message->is_system_message) {
            return $message->getSystemMessageContent();
        }

        if ($message->is_voice_message) {
            return '🎤 Voice message';
        }

        if ($message->isImage()) {
            return '📸 Image';
        }

        if ($message->isVideo()) {
            return '🎥 Video';
        }

        if ($message->isFile()) {
            return '📎 File';
        }

        return substr($message->content, 0, 50) . (strlen($message->content) > 50 ? '...' : '');
    }

    public function addParticipant(User $user, string $role = 'member'): bool
    {
        // For this schema, participants are already defined by user_one_id and user_two_id
        // This method is kept for compatibility but doesn't need to do anything
        return true;
    }

    public function removeParticipant(User $user): bool
    {
        // For this schema, participants are already defined by user_one_id and user_two_id
        // This method is kept for compatibility but doesn't need to do anything
        return true;
    }

    public function archive(User $user): bool
    {
        return $this->update([
            'status' => self::STATUS_ARCHIVED,
        ]);
    }

    public function unarchive(): bool
    {
        return $this->update([
            'status' => self::STATUS_ACTIVE,
        ]);
    }

    public function block(User $user, string $reason = null): bool
    {
        return $this->update([
            'status' => self::STATUS_BLOCKED,
            'blocked_at' => now(),
            'blocked_by' => $user->id,
            'block_reason' => $reason,
        ]);
    }

    public function unblock(): bool
    {
        return $this->update([
            'status' => self::STATUS_ACTIVE,
            'blocked_at' => null,
            'blocked_by' => null,
            'block_reason' => null,
        ]);
    }

    public function softDelete(User $user): bool
    {
        return $this->update([
            'status' => self::STATUS_DELETED,
        ]);
    }

    public function restore(): bool
    {
        return $this->update([
            'status' => self::STATUS_ACTIVE,
        ]);
    }

    public function updateLastMessage(Message $message): bool
    {
        return $this->update([
            'last_message_at' => $message->created_at,
            'last_message_id' => $message->id,
        ]);
    }

    public function incrementUnreadCount(User $user): bool
    {
        if ($this->user_one_id === $user->id) {
            $this->increment('user_one_unread_count');
        } elseif ($this->user_two_id === $user->id) {
            $this->increment('user_two_unread_count');
        }
        return true;
    }

    public function resetUnreadCount(User $user): bool
    {
        if ($this->user_one_id === $user->id) {
            $this->update(['user_one_unread_count' => 0]);
        } elseif ($this->user_two_id === $user->id) {
            $this->update(['user_two_unread_count' => 0]);
        }
        return true;
    }

    public function muteParticipant(User $user, ?string $until = null): bool
    {
        // Muting is not implemented in this schema
        return true;
    }

    public function unmuteParticipant(User $user): bool
    {
        // Muting is not implemented in this schema
        return true;
    }

    public function isParticipantMuted(User $user): bool
    {
        // Muting is not implemented in this schema
        return false;
    }

    public function getParticipantRole(User $user): ?string
    {
        if ($this->created_by === $user->id) {
            return 'creator';
        }
        return 'member';
    }

    public function canSendMessage(User $user): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        if ($this->isBlocked()) {
            return false;
        }

        return $this->user_one_id === $user->id || $this->user_two_id === $user->id;
    }

    public function getSettings(): array
    {
        return $this->conversation_settings ?? [];
    }

    public function updateSettings(array $settings): bool
    {
        return $this->update([
            'conversation_settings' => array_merge($this->getSettings(), $settings)
        ]);
    }

    // Static methods for creating conversations
    public static function createDirectConversation(User $user1, User $user2): self
    {
        $conversation = self::create([
            'user_one_id' => $user1->id,
            'user_two_id' => $user2->id,
            'type' => self::TYPE_MATCH,
            'is_group' => false,
            'status' => self::STATUS_ACTIVE,
            'created_by' => $user1->id,
        ]);

        return $conversation;
    }

    public static function createGroupConversation(User $creator, array $participants, string $name = null): self
    {
        // For group conversations, we'll use the creator as user_one and set user_two to null
        // We'll need to modify the migration to allow nullable user_two_id for groups
        $conversation = self::create([
            'user_one_id' => $creator->id,
            'user_two_id' => null, // Groups don't have a specific second user
            'name' => $name,
            'type' => self::TYPE_MATCH,
            'is_group' => true,
            'status' => self::STATUS_ACTIVE,
            'created_by' => $creator->id,
        ]);

        return $conversation;
    }

    public static function createMatchConversation(UserMatch $match): self
    {
        $conversation = self::create([
            'user_one_id' => $match->user_id,
            'user_two_id' => $match->matched_user_id,
            'match_id' => $match->id,
            'type' => self::TYPE_MATCH,
            'is_group' => false,
            'status' => self::STATUS_ACTIVE,
            'created_by' => $match->user_id,
            'metadata' => [
                'match_id' => $match->id,
                'match_score' => $match->compatibility_score,
            ]
        ]);

        // Create system message for match
        Message::createMatchCreatedMessage($conversation);

        return $conversation;
    }

    public static function findDirectConversation(User $user1, User $user2): ?self
    {
        return self::where('type', self::TYPE_MATCH)
                  ->where('is_group', false)
                  ->where(function ($q) use ($user1, $user2) {
                      $q->where(function ($subQ) use ($user1, $user2) {
                          $subQ->where('user_one_id', $user1->id)
                               ->where('user_two_id', $user2->id);
                      })->orWhere(function ($subQ) use ($user1, $user2) {
                          $subQ->where('user_one_id', $user2->id)
                               ->where('user_two_id', $user1->id);
                      });
                  })
                  ->first();
    }

    public static function getOrCreateDirectConversation(User $user1, User $user2): self
    {
        $conversation = self::findDirectConversation($user1, $user2);
        
        if (!$conversation) {
            $conversation = self::createDirectConversation($user1, $user2);
        }
        
        return $conversation;
    }
}
