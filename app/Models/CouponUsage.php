<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'coupon_id',
        'user_id',
        'subscription_id',
        'original_amount',
        'discount_amount',
        'final_amount',
        'currency',
        'payment_gateway',
        'transaction_id',
    ];

    protected $casts = [
        'original_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
    ];

    /**
     * Get the coupon that was used
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Get the user who used the coupon
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subscription this usage was applied to
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Calculate discount percentage
     */
    public function getDiscountPercentageAttribute(): float
    {
        if ($this->original_amount > 0) {
            return ($this->discount_amount / $this->original_amount) * 100;
        }
        return 0;
    }

    /**
     * Get usage statistics for a user
     */
    public static function getUserUsageStats(User $user): array
    {
        $usages = static::where('user_id', $user->id)->get();

        return [
            'total_coupons_used' => $usages->count(),
            'total_savings' => $usages->sum('discount_amount'),
            'average_savings' => $usages->avg('discount_amount'),
            'total_orders_with_coupons' => $usages->count(),
            'favorite_coupon_types' => $usages->groupBy(function ($usage) {
                return $usage->coupon->type;
            })->map->count()->sortDesc(),
        ];
    }

    /**
     * Get usage analytics for admin
     */
    public static function getAnalytics(int $days = 30): array
    {
        $startDate = now()->subDays($days);
        $usages = static::where('created_at', '>=', $startDate)->get();

        return [
            'total_usages' => $usages->count(),
            'total_discount_given' => $usages->sum('discount_amount'),
            'unique_users' => $usages->unique('user_id')->count(),
            'average_discount' => $usages->avg('discount_amount'),
            'usage_by_payment_gateway' => $usages->groupBy('payment_gateway')->map->count(),
            'usage_by_currency' => $usages->groupBy('currency')->map->count(),
            'daily_usage' => $usages->groupBy(function ($usage) {
                return $usage->created_at->format('Y-m-d');
            })->map->count(),
        ];
    }
} 