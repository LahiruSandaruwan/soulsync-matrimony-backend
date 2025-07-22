<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class TwoFactorCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'code',
        'type',
        'expires_at',
        'used',
        'used_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'used' => 'boolean',
    ];

    /**
     * Get the user that owns the 2FA code
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a new 2FA code
     */
    public static function generateFor(User $user, string $type = 'login', int $expiryMinutes = 10): self
    {
        // Delete any existing codes of the same type for this user
        static::where('user_id', $user->id)
            ->where('type', $type)
            ->where('used', false)
            ->delete();

        $code = static::generateSecureCode();

        return static::create([
            'user_id' => $user->id,
            'code' => $code,
            'type' => $type,
            'expires_at' => now()->addMinutes($expiryMinutes),
        ]);
    }

    /**
     * Generate a secure random code
     */
    private static function generateSecureCode(): string
    {
        return str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a code for a user
     */
    public static function verify(User $user, string $code, string $type = 'login'): bool
    {
        $codeRecord = static::where('user_id', $user->id)
            ->where('code', $code)
            ->where('type', $type)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->first();

        if ($codeRecord) {
            $codeRecord->update([
                'used' => true,
                'used_at' => now(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return true;
        }

        return false;
    }

    /**
     * Check if code is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if code is used
     */
    public function isUsed(): bool
    {
        return $this->used;
    }

    /**
     * Check if code is valid
     */
    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isUsed();
    }

    /**
     * Get remaining time for code expiry
     */
    public function getRemainingTime(): int
    {
        if ($this->isExpired()) {
            return 0;
        }

        return $this->expires_at->diffInSeconds(now());
    }

    /**
     * Scope for valid codes
     */
    public function scopeValid($query)
    {
        return $query->where('used', false)
            ->where('expires_at', '>', now());
    }

    /**
     * Scope for expired codes
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Clean up expired codes
     */
    public static function cleanupExpired(): int
    {
        return static::where('expires_at', '<=', now()->subDay())->delete();
    }

    /**
     * Get code attempts for rate limiting
     */
    public static function getRecentAttempts(User $user, string $type = 'login', int $minutes = 60): int
    {
        return static::where('user_id', $user->id)
            ->where('type', $type)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->count();
    }

    /**
     * Check if user has exceeded code generation limit
     */
    public static function hasExceededGenerationLimit(User $user, string $type = 'login'): bool
    {
        $recentCodes = static::getRecentAttempts($user, $type, 60);
        return $recentCodes >= 5; // Max 5 codes per hour
    }

    /**
     * Send code via SMS
     */
    public function sendViaSms(): bool
    {
        try {
            $phone = $this->user->twoFactorAuth->phone ?? $this->user->phone;
            
            if (!$phone) {
                return false;
            }

            // Send SMS using CommunicationService
            $communicationService = app(\App\Services\CommunicationService::class);
            return $communicationService->send2FASMS($phone, $this->code);

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to send 2FA SMS', [
                'user_id' => $this->user_id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send code via email
     */
    public function sendViaEmail(): bool
    {
        try {
            // Send email using CommunicationService
            $communicationService = app(\App\Services\CommunicationService::class);
            return $communicationService->send2FAEmail($this->user->email, $this->code);

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to send 2FA Email', [
                'user_id' => $this->user_id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
} 