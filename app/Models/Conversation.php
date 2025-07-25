<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
        'name',
        'type',
        'is_group',
        'is_archived',
        'archived_at',
        'archived_by',
        'is_blocked',
        'blocked_at',
        'blocked_by',
        'blocked_reason',
        'last_message_at',
        'last_message_id',
        'unread_count',
        'metadata',
        'settings',
        'created_by',
        'updated_by',
        'deleted_at',
        'deleted_by'
    ];

    protected $casts = [
        'metadata' => 'array',
        'settings' => 'array',
        'is_group' => 'boolean',
        'is_archived' => 'boolean',
        'is_blocked' => 'boolean',
        'unread_count' => 'integer',
        'archived_at' => 'datetime',
        'blocked_at' => 'datetime',
        'last_message_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $dates = [
        'archived_at',
        'blocked_at',
        'last_message_at',
        'deleted_at',
    ];

    // Conversation Types
    const TYPE_DIRECT = 'direct';
    const TYPE_GROUP = 'group';
    const TYPE_MATCH = 'match';
    const TYPE_SUPPORT = 'support';

    // Conversation Status
    const STATUS_ACTIVE = 'active';
    const STATUS_ARCHIVED = 'archived';
    const STATUS_BLOCKED = 'blocked';
    const STATUS_DELETED = 'deleted';

    // Relationships
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
                    ->withPivot(['role', 'joined_at', 'left_at', 'is_muted', 'muted_until'])
                    ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latest();
    }

    public function createdBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
                    ->wherePivot('role', 'creator');
    }

    public function updatedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
                    ->wherePivot('role', 'admin');
    }

    public function archivedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
                    ->wherePivot('role', 'archiver');
    }

    public function blockedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
                    ->wherePivot('role', 'blocker');
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
        return $query->where('is_archived', false)
                    ->where('is_blocked', false)
                    ->whereNull('deleted_at');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('is_archived', true);
    }

    public function scopeBlocked(Builder $query): Builder
    {
        return $query->where('is_blocked', true);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeDirect(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_DIRECT);
    }

    public function scopeGroup(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_GROUP);
    }

    public function scopeMatch(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_MATCH);
    }

    public function scopeSupport(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_SUPPORT);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->whereHas('participants', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });
    }

    public function scopeWithUnreadMessages(Builder $query, User $user): Builder
    {
        return $query->whereHas('participants', function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->where('unread_count', '>', 0);
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
        return $query->orderBy('unread_count', 'desc');
    }

    // Methods
    public function isDirect(): bool
    {
        return $this->type === self::TYPE_DIRECT;
    }

    public function isGroup(): bool
    {
        return $this->type === self::TYPE_GROUP;
    }

    public function isMatch(): bool
    {
        return $this->type === self::TYPE_MATCH;
    }

    public function isSupport(): bool
    {
        return $this->type === self::TYPE_SUPPORT;
    }

    public function isActive(): bool
    {
        return !$this->is_archived && !$this->is_blocked && !$this->deleted_at;
    }

    public function isArchived(): bool
    {
        return $this->is_archived;
    }

    public function isBlocked(): bool
    {
        return $this->is_blocked;
    }

    public function isDeleted(): bool
    {
        return !is_null($this->deleted_at);
    }

    public function hasUnreadMessages(User $user): bool
    {
        $participant = $this->participants()->where('user_id', $user->id)->first();
        return $participant && $participant->pivot->unread_count > 0;
    }

    public function getUnreadCount(User $user): int
    {
        $participant = $this->participants()->where('user_id', $user->id)->first();
        return $participant ? $participant->pivot->unread_count : 0;
    }

    public function getOtherParticipant(User $user): ?User
    {
        if ($this->isDirect()) {
            return $this->participants()->where('user_id', '!=', $user->id)->first();
        }
        return null;
    }

    public function getDisplayName(User $user): string
    {
        if ($this->name) {
            return $this->name;
        }

        if ($this->isDirect()) {
            $otherUser = $this->getOtherParticipant($user);
            return $otherUser ? $otherUser->first_name : 'Unknown User';
        }

        if ($this->isGroup()) {
            $participantNames = $this->participants()
                ->where('user_id', '!=', $user->id)
                ->pluck('first_name')
                ->take(3)
                ->toArray();
            
            return implode(', ', $participantNames) . ($this->participants()->count() > 4 ? '...' : '');
        }

        return 'Conversation';
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
        if ($this->participants()->where('user_id', $user->id)->exists()) {
            return false;
        }

        $this->participants()->attach($user->id, [
            'role' => $role,
            'joined_at' => now(),
            'is_muted' => false,
        ]);

        return true;
    }

    public function removeParticipant(User $user): bool
    {
        $this->participants()->updateExistingPivot($user->id, [
            'left_at' => now(),
        ]);

        return true;
    }

    public function archive(User $user): bool
    {
        return $this->update([
            'is_archived' => true,
            'archived_at' => now(),
            'archived_by' => $user->id,
        ]);
    }

    public function unarchive(): bool
    {
        return $this->update([
            'is_archived' => false,
            'archived_at' => null,
            'archived_by' => null,
        ]);
    }

    public function block(User $user, string $reason = null): bool
    {
        return $this->update([
            'is_blocked' => true,
            'blocked_at' => now(),
            'blocked_by' => $user->id,
            'blocked_reason' => $reason,
        ]);
    }

    public function unblock(): bool
    {
        return $this->update([
            'is_blocked' => false,
            'blocked_at' => null,
            'blocked_by' => null,
            'blocked_reason' => null,
        ]);
    }

    public function softDelete(User $user): bool
    {
        return $this->update([
            'deleted_at' => now(),
            'deleted_by' => $user->id,
        ]);
    }

    public function restore(): bool
    {
        return $this->update([
            'deleted_at' => null,
            'deleted_by' => null,
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
        $this->participants()->updateExistingPivot($user->id, [
            'unread_count' => \DB::raw('unread_count + 1'),
        ]);

        return true;
    }

    public function resetUnreadCount(User $user): bool
    {
        $this->participants()->updateExistingPivot($user->id, [
            'unread_count' => 0,
        ]);

        return true;
    }

    public function muteParticipant(User $user, ?string $until = null): bool
    {
        $this->participants()->updateExistingPivot($user->id, [
            'is_muted' => true,
            'muted_until' => $until,
        ]);

        return true;
    }

    public function unmuteParticipant(User $user): bool
    {
        $this->participants()->updateExistingPivot($user->id, [
            'is_muted' => false,
            'muted_until' => null,
        ]);

        return true;
    }

    public function isParticipantMuted(User $user): bool
    {
        $participant = $this->participants()->where('user_id', $user->id)->first();
        
        if (!$participant) {
            return false;
        }

        if (!$participant->pivot->is_muted) {
            return false;
        }

        if ($participant->pivot->muted_until && now()->gt($participant->pivot->muted_until)) {
            // Auto-unmute if mute period has expired
            $this->unmuteParticipant($user);
            return false;
        }

        return true;
    }

    public function getParticipantRole(User $user): ?string
    {
        $participant = $this->participants()->where('user_id', $user->id)->first();
        return $participant ? $participant->pivot->role : null;
    }

    public function canSendMessage(User $user): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        if ($this->isParticipantMuted($user)) {
            return false;
        }

        if ($this->isBlocked()) {
            return false;
        }

        return $this->participants()->where('user_id', $user->id)->exists();
    }

    public function getSettings(): array
    {
        return $this->settings ?? [];
    }

    public function updateSettings(array $settings): bool
    {
        return $this->update([
            'settings' => array_merge($this->getSettings(), $settings)
        ]);
    }

    // Static methods for creating conversations
    public static function createDirectConversation(User $user1, User $user2): self
    {
        $conversation = self::create([
            'type' => self::TYPE_DIRECT,
            'is_group' => false,
            'created_by' => $user1->id,
        ]);

        // Add participants
        $conversation->addParticipant($user1, 'creator');
        $conversation->addParticipant($user2, 'member');

        return $conversation;
    }

    public static function createGroupConversation(User $creator, array $participants, string $name = null): self
    {
        $conversation = self::create([
            'name' => $name,
            'type' => self::TYPE_GROUP,
            'is_group' => true,
            'created_by' => $creator->id,
        ]);

        // Add creator
        $conversation->addParticipant($creator, 'creator');

        // Add other participants
        foreach ($participants as $participant) {
            if ($participant->id !== $creator->id) {
                $conversation->addParticipant($participant, 'member');
            }
        }

        return $conversation;
    }

    public static function createMatchConversation(UserMatch $match): self
    {
        $conversation = self::create([
            'type' => self::TYPE_MATCH,
            'is_group' => false,
            'created_by' => $match->user_id,
            'metadata' => [
                'match_id' => $match->id,
                'match_score' => $match->compatibility_score,
            ]
        ]);

        // Add both users from the match
        $conversation->addParticipant($match->user, 'member');
        $conversation->addParticipant($match->matchedUser, 'member');

        // Create system message for match
        Message::createMatchCreatedMessage($conversation);

        return $conversation;
    }

    public static function findDirectConversation(User $user1, User $user2): ?self
    {
        return self::where('type', self::TYPE_DIRECT)
                  ->where('is_group', false)
                  ->whereHas('participants', function ($q) use ($user1) {
                      $q->where('user_id', $user1->id);
                  })
                  ->whereHas('participants', function ($q) use ($user2) {
                      $q->where('user_id', $user2->id);
                  })
                  ->whereDoesntHave('participants', function ($q) use ($user1, $user2) {
                      $q->whereNotIn('user_id', [$user1->id, $user2->id]);
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
