<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TwoFactorAuth extends Model
{
    use HasFactory;

    protected $table = 'two_factor_auth';

    protected $fillable = [
        'user_id',
        'enabled',
        'secret',
        'recovery_codes',
        'enabled_at',
        'method',
        'phone',
        'phone_verified',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'phone_verified' => 'boolean',
        'enabled_at' => 'datetime',
        'recovery_codes' => 'array',
    ];

    protected $hidden = [
        'secret',
        'recovery_codes',
    ];

    /**
     * Get the user that owns the 2FA configuration
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the 2FA codes for this configuration
     */
    public function codes(): HasMany
    {
        return $this->hasMany(TwoFactorCode::class, 'user_id', 'user_id');
    }

    /**
     * Generate recovery codes
     */
    public function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(6))), 0, 8));
        }

        $this->update(['recovery_codes' => $codes]);
        return $codes;
    }

    /**
     * Use a recovery code
     */
    public function useRecoveryCode(string $code): bool
    {
        $codes = $this->recovery_codes ?? [];
        $codeIndex = array_search(strtoupper($code), $codes);

        if ($codeIndex !== false) {
            unset($codes[$codeIndex]);
            $this->update(['recovery_codes' => array_values($codes)]);
            return true;
        }

        return false;
    }

    /**
     * Check if recovery codes are running low
     */
    public function hasLowRecoveryCodes(): bool
    {
        return count($this->recovery_codes ?? []) <= 2;
    }

    /**
     * Enable 2FA
     */
    public function enable(): void
    {
        $this->update([
            'enabled' => true,
            'enabled_at' => now(),
        ]);
    }

    /**
     * Disable 2FA
     */
    public function disable(): void
    {
        $this->update([
            'enabled' => false,
            'enabled_at' => null,
            'secret' => null,
            'recovery_codes' => null,
        ]);

        // Delete any pending codes
        $this->codes()->delete();
    }

    /**
     * Check if 2FA is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get QR code URL for TOTP setup
     */
    public function getQrCodeUrl(): string
    {
        $issuer = config('app.name', 'SoulSync');
        $label = $this->user->email;
        
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            $issuer,
            $label,
            $this->secret,
            $issuer
        );
    }

    /**
     * Verify TOTP code
     */
    public function verifyTotp(string $code): bool
    {
        if (!$this->secret) {
            return false;
        }

        // This would use a TOTP library like BaconQrCode/Google2FA
        // For now, we'll implement basic time-based validation
        $timeSlice = floor(time() / 30);
        
        // Check current time slice and ±1 for clock drift
        for ($i = -1; $i <= 1; $i++) {
            $calculatedCode = $this->generateTotpCode($timeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate TOTP code for a time slice
     */
    private function generateTotpCode(int $timeSlice): string
    {
        // Simplified TOTP implementation
        // In production, use Google2FA or similar library
        $key = base32_decode($this->secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;

        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }
}

// Helper function for base32 decoding
if (!function_exists('base32_decode')) {
    function base32_decode($input) {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output = '';
        $v = 0;
        $vbits = 0;
        
        for ($i = 0, $j = strlen($input); $i < $j; $i++) {
            $v <<= 5;
            $v += strpos($alphabet, $input[$i]);
            $vbits += 5;
            
            while ($vbits >= 8) {
                $output .= chr($v >> ($vbits - 8));
                $vbits -= 8;
            }
        }
        
        return $output;
    }
} 