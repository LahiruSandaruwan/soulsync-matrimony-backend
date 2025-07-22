<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'minimum_amount',
        'maximum_discount',
        'applicable_plans',
        'usage_limit',
        'usage_limit_per_user',
        'used_count',
        'is_active',
        'starts_at',
        'expires_at',
        'created_by',
    ];

    protected $casts = [
        'applicable_plans' => 'array',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'value' => 'decimal:2',
        'minimum_amount' => 'decimal:2',
        'maximum_discount' => 'decimal:2',
    ];

    /**
     * Get the user who created this coupon
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the coupon usages
     */
    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    /**
     * Check if coupon is valid for use
     */
    public function isValid(User $user = null, string $planType = null, float $amount = null): array
    {
        $errors = [];

        // Check if coupon is active
        if (!$this->is_active) {
            $errors[] = 'This coupon is no longer active.';
        }

        // Check start date
        if ($this->starts_at && $this->starts_at->isFuture()) {
            $errors[] = 'This coupon is not yet valid.';
        }

        // Check expiry date
        if ($this->expires_at && $this->expires_at->isPast()) {
            $errors[] = 'This coupon has expired.';
        }

        // Check usage limit
        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            $errors[] = 'This coupon has reached its usage limit.';
        }

        // Check user-specific usage limit
        if ($user && $this->usage_limit_per_user) {
            $userUsageCount = $this->usages()->where('user_id', $user->id)->count();
            if ($userUsageCount >= $this->usage_limit_per_user) {
                $errors[] = 'You have already used this coupon the maximum number of times.';
            }
        }

        // Check applicable plans
        if ($planType && $this->applicable_plans && !in_array($planType, $this->applicable_plans)) {
            $errors[] = 'This coupon is not applicable to the selected plan.';
        }

        // Check minimum amount
        if ($amount && $this->minimum_amount && $amount < $this->minimum_amount) {
            $errors[] = "Minimum order amount of \${$this->minimum_amount} required for this coupon.";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Apply coupon discount to amount
     */
    public function applyDiscount(float $amount): array
    {
        $discountAmount = 0;
        
        switch ($this->type) {
            case 'percentage':
                $discountAmount = ($amount * $this->value) / 100;
                
                // Apply maximum discount limit
                if ($this->maximum_discount && $discountAmount > $this->maximum_discount) {
                    $discountAmount = $this->maximum_discount;
                }
                break;

            case 'fixed_amount':
                $discountAmount = min($this->value, $amount);
                break;

            case 'free_trial':
                // For free trial coupons, return special handling
                return [
                    'discount_amount' => $amount,
                    'final_amount' => 0,
                    'discount_type' => 'free_trial',
                    'free_trial_days' => $this->value,
                ];
        }

        $finalAmount = max(0, $amount - $discountAmount);

        return [
            'discount_amount' => $discountAmount,
            'final_amount' => $finalAmount,
            'discount_type' => $this->type,
            'discount_percentage' => $amount > 0 ? ($discountAmount / $amount) * 100 : 0,
        ];
    }

    /**
     * Use the coupon (record usage)
     */
    public function use(User $user, float $originalAmount, float $finalAmount, array $metadata = []): CouponUsage
    {
        $usage = $this->usages()->create([
            'user_id' => $user->id,
            'subscription_id' => $metadata['subscription_id'] ?? null,
            'original_amount' => $originalAmount,
            'discount_amount' => $originalAmount - $finalAmount,
            'final_amount' => $finalAmount,
            'currency' => $metadata['currency'] ?? 'USD',
            'payment_gateway' => $metadata['payment_gateway'] ?? null,
            'transaction_id' => $metadata['transaction_id'] ?? null,
        ]);

        // Increment usage count
        $this->increment('used_count');

        return $usage;
    }

    /**
     * Generate a random coupon code
     */
    public static function generateCode(int $length = 8): string
    {
        do {
            $code = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, $length));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /**
     * Find coupon by code
     */
    public static function findByCode(string $code): ?self
    {
        return static::where('code', strtoupper($code))
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get active coupons
     */
    public static function getActiveCoupons(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            })
            ->where(function ($query) {
                $query->whereNull('usage_limit')
                    ->orWhereColumn('used_count', '<', 'usage_limit');
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get coupon usage statistics
     */
    public function getUsageStats(): array
    {
        $usages = $this->usages;

        return [
            'total_uses' => $usages->count(),
            'unique_users' => $usages->unique('user_id')->count(),
            'total_discount_given' => $usages->sum('discount_amount'),
            'average_discount' => $usages->avg('discount_amount'),
            'usage_by_month' => $usages->groupBy(function ($usage) {
                return $usage->created_at->format('Y-m');
            })->map->count(),
            'remaining_uses' => $this->usage_limit ? max(0, $this->usage_limit - $this->used_count) : null,
        ];
    }

    /**
     * Create a promotional coupon
     */
    public static function createPromotionalCoupon(array $data): self
    {
        return static::create([
            'code' => $data['code'] ?? static::generateCode(),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'value' => $data['value'],
            'minimum_amount' => $data['minimum_amount'] ?? null,
            'maximum_discount' => $data['maximum_discount'] ?? null,
            'applicable_plans' => $data['applicable_plans'] ?? null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'usage_limit_per_user' => $data['usage_limit_per_user'] ?? 1,
            'is_active' => true,
            'starts_at' => $data['starts_at'] ?? now(),
            'expires_at' => $data['expires_at'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    /**
     * Deactivate expired coupons
     */
    public static function deactivateExpired(): int
    {
        return static::where('is_active', true)
            ->where('expires_at', '<', now())
            ->update(['is_active' => false]);
    }

    /**
     * Get popular coupons (most used)
     */
    public static function getPopularCoupons(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('is_active', true)
            ->orderBy('used_count', 'desc')
            ->limit($limit)
            ->get();
    }
} 