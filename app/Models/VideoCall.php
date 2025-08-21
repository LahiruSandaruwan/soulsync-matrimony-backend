<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoCall extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'caller_id',
        'callee_id',
        'conversation_id',
        'call_id',
        'room_id',
        'caller_token',
        'callee_token',
        'status',
        'initiated_at',
        'accepted_at',
        'ended_at',
        'duration_seconds',
        'end_reason',
        'quality_rating',
        'feedback',
        'recording_url',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'initiated_at' => 'datetime',
            'accepted_at' => 'datetime',
            'ended_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'caller_token',
        'callee_token',
        'recording_url',
    ];

    // Relationships

    /**
     * Get the caller (user who initiated the call).
     */
    public function caller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    /**
     * Get the callee (user who received the call).
     */
    public function callee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'callee_id');
    }

    /**
     * Get the conversation this call is associated with.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    // Scopes

    /**
     * Scope for pending calls.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for accepted calls.
     */
    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    /**
     * Scope for ended calls.
     */
    public function scopeEnded($query)
    {
        return $query->where('status', 'ended');
    }

    /**
     * Scope for calls involving a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where(function($q) use ($userId) {
            $q->where('caller_id', $userId)
              ->orWhere('callee_id', $userId);
        });
    }

    // Accessors & Mutators

    /**
     * Get the duration in a human-readable format.
     */
    public function getDurationFormattedAttribute(): string
    {
        if (!$this->duration_seconds) {
            return '00:00';
        }

        $minutes = floor($this->duration_seconds / 60);
        $seconds = $this->duration_seconds % 60;

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    /**
     * Check if the call is ongoing.
     */
    public function getIsOngoingAttribute(): bool
    {
        return in_array($this->status, ['pending', 'accepted']);
    }

    /**
     * Check if the call was successful (accepted and had some duration).
     */
    public function getWasSuccessfulAttribute(): bool
    {
        return $this->status === 'ended' && $this->duration_seconds > 0;
    }

    // Methods

    /**
     * Mark the call as missed.
     */
    public function markAsMissed(): bool
    {
        return $this->update([
            'status' => 'missed',
            'ended_at' => now(),
        ]);
    }

    /**
     * Get the other participant in the call.
     */
    public function getOtherParticipant(int $userId): ?User
    {
        if ($this->caller_id === $userId) {
            return $this->callee;
        } elseif ($this->callee_id === $userId) {
            return $this->caller;
        }

        return null;
    }

    /**
     * Check if a user is part of this call.
     */
    public function hasParticipant(int $userId): bool
    {
        return $this->caller_id === $userId || $this->callee_id === $userId;
    }

    /**
     * Rate the call quality.
     */
    public function rateQuality(int $rating, string $feedback = null): bool
    {
        if ($rating < 1 || $rating > 5) {
            return false;
        }

        return $this->update([
            'quality_rating' => $rating,
            'feedback' => $feedback,
        ]);
    }
}
