<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'content',
        'message_type',
        'is_edited',
        'edited_at',
        'is_deleted',
        'deleted_at',
        'deleted_by',
        'read_at',
        'delivered_at',
        'metadata',
        'reply_to_message_id',
        'forwarded_from_message_id',
        'attachment_type',
        'attachment_url',
        'attachment_metadata',
        'voice_duration',
        'voice_url',
        'is_voice_message',
        'is_system_message',
        'system_message_type',
        'encryption_key',
        'is_encrypted'
    ];

    protected $casts = [
        'metadata' => 'array',
        'attachment_metadata' => 'array',
        'is_edited' => 'boolean',
        'is_deleted' => 'boolean',
        'is_voice_message' => 'boolean',
        'is_system_message' => 'boolean',
        'is_encrypted' => 'boolean',
        'edited_at' => 'datetime',
        'deleted_at' => 'datetime',
        'read_at' => 'datetime',
        'delivered_at' => 'datetime',
        'voice_duration' => 'integer',
    ];

    protected $dates = [
        'edited_at',
        'deleted_at',
        'read_at',
        'delivered_at',
    ];

    // Message Types
    const TYPE_TEXT = 'text';
    const TYPE_IMAGE = 'image';
    const TYPE_VOICE = 'voice';
    const TYPE_VIDEO = 'video';
    const TYPE_FILE = 'file';
    const TYPE_LOCATION = 'location';
    const TYPE_CONTACT = 'contact';
    const TYPE_STICKER = 'sticker';
    const TYPE_SYSTEM = 'system';

    // System Message Types
    const SYSTEM_MATCH_CREATED = 'match_created';
    const SYSTEM_PROFILE_VIEWED = 'profile_viewed';
    const SYSTEM_SUBSCRIPTION_ACTIVATED = 'subscription_activated';
    const SYSTEM_SUBSCRIPTION_EXPIRED = 'subscription_expired';
    const SYSTEM_ACCOUNT_VERIFIED = 'account_verified';
    const SYSTEM_PHOTO_APPROVED = 'photo_approved';
    const SYSTEM_PHOTO_REJECTED = 'photo_rejected';

    // Attachment Types
    const ATTACHMENT_IMAGE = 'image';
    const ATTACHMENT_VIDEO = 'video';
    const ATTACHMENT_AUDIO = 'audio';
    const ATTACHMENT_DOCUMENT = 'document';
    const ATTACHMENT_LOCATION = 'location';
    const ATTACHMENT_CONTACT = 'contact';

    // Relationships
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_message_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'reply_to_message_id');
    }

    public function forwardedFrom(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'forwarded_from_message_id');
    }

    public function forwardedTo(): HasMany
    {
        return $this->hasMany(Message::class, 'forwarded_from_message_id');
    }

    public function readBy(): HasMany
    {
        return $this->hasMany(MessageRead::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'source');
    }

    // Scopes
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('message_type', $type);
    }

    public function scopeText(Builder $query): Builder
    {
        return $query->where('message_type', self::TYPE_TEXT);
    }

    public function scopeMedia(Builder $query): Builder
    {
        return $query->whereIn('message_type', [
            self::TYPE_IMAGE,
            self::TYPE_VOICE,
            self::TYPE_VIDEO,
            self::TYPE_FILE
        ]);
    }

    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system_message', true);
    }

    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where('is_deleted', false);
    }

    public function scopeDeleted(Builder $query): Builder
    {
        return $query->where('is_deleted', true);
    }

    public function scopeEdited(Builder $query): Builder
    {
        return $query->where('is_edited', true);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeRead(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
    }

    public function scopeDelivered(Builder $query): Builder
    {
        return $query->whereNotNull('delivered_at');
    }

    public function scopeUndelivered(Builder $query): Builder
    {
        return $query->whereNull('delivered_at');
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeBySender(Builder $query, int $senderId): Builder
    {
        return $query->where('sender_id', $senderId);
    }

    public function scopeWithAttachments(Builder $query): Builder
    {
        return $query->whereNotNull('attachment_url');
    }

    public function scopeVoiceMessages(Builder $query): Builder
    {
        return $query->where('is_voice_message', true);
    }

    // Methods
    public function isText(): bool
    {
        return $this->message_type === self::TYPE_TEXT;
    }

    public function isImage(): bool
    {
        return $this->message_type === self::TYPE_IMAGE;
    }

    public function isVoice(): bool
    {
        return $this->message_type === self::TYPE_VOICE || $this->is_voice_message;
    }

    public function isVideo(): bool
    {
        return $this->message_type === self::TYPE_VIDEO;
    }

    public function isFile(): bool
    {
        return $this->message_type === self::TYPE_FILE;
    }

    public function isSystem(): bool
    {
        return $this->is_system_message;
    }

    public function isEdited(): bool
    {
        return $this->is_edited;
    }

    public function isDeleted(): bool
    {
        return $this->is_deleted;
    }

    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }

    public function isDelivered(): bool
    {
        return !is_null($this->delivered_at);
    }

    public function hasAttachment(): bool
    {
        return !is_null($this->attachment_url);
    }

    public function isReply(): bool
    {
        return !is_null($this->reply_to_message_id);
    }

    public function isForwarded(): bool
    {
        return !is_null($this->forwarded_from_message_id);
    }

    public function markAsRead(): bool
    {
        return $this->update(['read_at' => now()]);
    }

    public function markAsDelivered(): bool
    {
        return $this->update(['delivered_at' => now()]);
    }

    public function edit(string $newContent): bool
    {
        return $this->update([
            'content' => $newContent,
            'is_edited' => true,
            'edited_at' => now()
        ]);
    }

    public function softDelete(int $deletedBy): bool
    {
        return $this->update([
            'is_deleted' => true,
            'deleted_at' => now(),
            'deleted_by' => $deletedBy
        ]);
    }

    public function restore(): bool
    {
        return $this->update([
            'is_deleted' => false,
            'deleted_at' => null,
            'deleted_by' => null
        ]);
    }

    public function getFormattedContent(): string
    {
        if ($this->is_deleted) {
            return '[Message deleted]';
        }

        if ($this->is_system_message) {
            return $this->getSystemMessageContent();
        }

        return $this->content;
    }

    public function getSystemMessageContent(): string
    {
        return match($this->system_message_type) {
            self::SYSTEM_MATCH_CREATED => 'You have a new match! 🎉',
            self::SYSTEM_PROFILE_VIEWED => 'Someone viewed your profile 👀',
            self::SYSTEM_SUBSCRIPTION_ACTIVATED => 'Your subscription is now active! ✨',
            self::SYSTEM_SUBSCRIPTION_EXPIRED => 'Your subscription has expired 📅',
            self::SYSTEM_ACCOUNT_VERIFIED => 'Your account has been verified! ✅',
            self::SYSTEM_PHOTO_APPROVED => 'Your photo has been approved! 📸',
            self::SYSTEM_PHOTO_REJECTED => 'Your photo was not approved 📸',
            default => 'System message'
        };
    }

    public function getAttachmentInfo(): ?array
    {
        if (!$this->hasAttachment()) {
            return null;
        }

        return [
            'type' => $this->attachment_type,
            'url' => $this->attachment_url,
            'metadata' => $this->attachment_metadata,
            'size' => $this->attachment_metadata['size'] ?? null,
            'filename' => $this->attachment_metadata['filename'] ?? null,
            'mime_type' => $this->attachment_metadata['mime_type'] ?? null,
        ];
    }

    public function getVoiceInfo(): ?array
    {
        if (!$this->isVoice()) {
            return null;
        }

        return [
            'url' => $this->voice_url,
            'duration' => $this->voice_duration,
            'duration_formatted' => $this->formatDuration($this->voice_duration),
        ];
    }

    public function getReplyInfo(): ?array
    {
        if (!$this->isReply() || !$this->replyTo) {
            return null;
        }

        return [
            'id' => $this->replyTo->id,
            'content' => $this->replyTo->getFormattedContent(),
            'sender_name' => $this->replyTo->sender->first_name,
            'message_type' => $this->replyTo->message_type,
            'is_deleted' => $this->replyTo->is_deleted,
        ];
    }

    public function getForwardInfo(): ?array
    {
        if (!$this->isForwarded() || !$this->forwardedFrom) {
            return null;
        }

        return [
            'id' => $this->forwardedFrom->id,
            'content' => $this->forwardedFrom->getFormattedContent(),
            'sender_name' => $this->forwardedFrom->sender->first_name,
            'conversation_name' => $this->forwardedFrom->conversation->name ?? 'Unknown',
            'forwarded_at' => $this->created_at,
        ];
    }

    public function canBeEditedBy(User $user): bool
    {
        // Only sender can edit, within 5 minutes of sending
        return $this->sender_id === $user->id && 
               $this->created_at->diffInMinutes(now()) <= 5 &&
               !$this->is_deleted &&
               !$this->is_system_message;
    }

    public function canBeDeletedBy(User $user): bool
    {
        // Sender can delete anytime, recipient can delete if read
        return $this->sender_id === $user->id || 
               ($this->isRead() && $this->conversation->participants->contains($user->id));
    }

    public function getReactionCount(string $reaction = null): int
    {
        $query = $this->reactions();
        
        if ($reaction) {
            $query->where('reaction', $reaction);
        }
        
        return $query->count();
    }

    public function getUserReaction(User $user): ?string
    {
        $reaction = $this->reactions()->where('user_id', $user->id)->first();
        return $reaction ? $reaction->reaction : null;
    }

    private function formatDuration(int $seconds): string
    {
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        
        return sprintf('%d:%02d', $minutes, $remainingSeconds);
    }

    // Static methods for creating messages
    public static function createTextMessage(
        Conversation $conversation,
        User $sender,
        string $content,
        ?int $replyToMessageId = null
    ): self {
        return self::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'content' => $content,
            'message_type' => self::TYPE_TEXT,
            'reply_to_message_id' => $replyToMessageId,
            'metadata' => [
                'word_count' => str_word_count($content),
                'character_count' => strlen($content),
            ]
        ]);
    }

    public static function createImageMessage(
        Conversation $conversation,
        User $sender,
        string $imageUrl,
        array $metadata = []
    ): self {
        return self::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'content' => '📸 Image',
            'message_type' => self::TYPE_IMAGE,
            'attachment_type' => self::ATTACHMENT_IMAGE,
            'attachment_url' => $imageUrl,
            'attachment_metadata' => array_merge($metadata, [
                'uploaded_at' => now()->toISOString(),
            ])
        ]);
    }

    public static function createVoiceMessage(
        Conversation $conversation,
        User $sender,
        string $voiceUrl,
        int $duration
    ): self {
        return self::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'content' => '🎤 Voice message',
            'message_type' => self::TYPE_VOICE,
            'is_voice_message' => true,
            'voice_url' => $voiceUrl,
            'voice_duration' => $duration,
            'attachment_type' => self::ATTACHMENT_AUDIO,
            'attachment_url' => $voiceUrl,
            'attachment_metadata' => [
                'duration' => $duration,
                'duration_formatted' => (new self())->formatDuration($duration),
                'uploaded_at' => now()->toISOString(),
            ]
        ]);
    }

    public static function createSystemMessage(
        Conversation $conversation,
        string $systemMessageType,
        array $metadata = []
    ): self {
        return self::create([
            'conversation_id' => $conversation->id,
            'sender_id' => null, // System messages have no sender
            'content' => (new self())->getSystemMessageContent(),
            'message_type' => self::TYPE_SYSTEM,
            'is_system_message' => true,
            'system_message_type' => $systemMessageType,
            'metadata' => $metadata,
            'read_at' => now(), // System messages are auto-read
            'delivered_at' => now(), // System messages are auto-delivered
        ]);
    }

    public static function createMatchCreatedMessage(Conversation $conversation): self
    {
        return self::createSystemMessage($conversation, self::SYSTEM_MATCH_CREATED);
    }

    public static function createProfileViewedMessage(Conversation $conversation, User $viewer): self
    {
        return self::createSystemMessage($conversation, self::SYSTEM_PROFILE_VIEWED, [
            'viewer_id' => $viewer->id,
            'viewer_name' => $viewer->first_name,
        ]);
    }
}
