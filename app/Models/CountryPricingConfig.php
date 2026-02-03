<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CountryPricingConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_code',
        'country_name',
        'currency_code',
        'currency_symbol',
        'basic_monthly',
        'basic_quarterly',
        'basic_yearly',
        'premium_monthly',
        'premium_quarterly',
        'premium_yearly',
        'platinum_monthly',
        'platinum_quarterly',
        'platinum_yearly',
        'quarterly_discount',
        'yearly_discount',
        'payment_methods',
        'tax_rate',
        'tax_name',
        'tax_inclusive',
        'display_order',
        'is_active',
        'is_default',
        'notes',
    ];

    protected $casts = [
        'basic_monthly' => 'decimal:2',
        'basic_quarterly' => 'decimal:2',
        'basic_yearly' => 'decimal:2',
        'premium_monthly' => 'decimal:2',
        'premium_quarterly' => 'decimal:2',
        'premium_yearly' => 'decimal:2',
        'platinum_monthly' => 'decimal:2',
        'platinum_quarterly' => 'decimal:2',
        'platinum_yearly' => 'decimal:2',
        'quarterly_discount' => 'decimal:2',
        'yearly_discount' => 'decimal:2',
        'payment_methods' => 'array',
        'tax_rate' => 'decimal:2',
        'tax_inclusive' => 'boolean',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Scope for active configs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope ordered by display order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('country_name');
    }

    /**
     * Get the default pricing config
     */
    public static function getDefault(): ?self
    {
        return self::where('is_default', true)->first()
            ?? self::where('country_code', 'US')->first();
    }

    /**
     * Get pricing config by country code
     */
    public static function getByCountry(string $countryCode): ?self
    {
        return self::active()
            ->where('country_code', strtoupper($countryCode))
            ->first();
    }

    /**
     * Get price for a specific plan and duration
     */
    public function getPrice(string $plan, string $duration): float
    {
        $column = strtolower($plan) . '_' . strtolower($duration);
        return (float) ($this->$column ?? 0);
    }

    /**
     * Get all prices for a plan
     */
    public function getPlanPrices(string $plan): array
    {
        $planLower = strtolower($plan);
        return [
            'monthly' => (float) $this->{$planLower . '_monthly'},
            'quarterly' => (float) $this->{$planLower . '_quarterly'},
            'yearly' => (float) $this->{$planLower . '_yearly'},
        ];
    }

    /**
     * Get formatted price with currency symbol
     */
    public function getFormattedPrice(string $plan, string $duration): string
    {
        $price = $this->getPrice($plan, $duration);
        return $this->currency_symbol . number_format($price, 2);
    }

    /**
     * Calculate price with tax
     */
    public function getPriceWithTax(string $plan, string $duration): float
    {
        $price = $this->getPrice($plan, $duration);

        if ($this->tax_inclusive || $this->tax_rate <= 0) {
            return $price;
        }

        return $price * (1 + ($this->tax_rate / 100));
    }

    /**
     * Check if a payment method is available
     */
    public function hasPaymentMethod(string $method): bool
    {
        $methods = $this->payment_methods ?? [];
        return in_array(strtolower($method), array_map('strtolower', $methods));
    }

    /**
     * Get all plan data formatted for API response
     */
    public function toPlansArray(): array
    {
        return [
            'country' => [
                'code' => $this->country_code,
                'name' => $this->country_name,
            ],
            'currency' => [
                'code' => $this->currency_code,
                'symbol' => $this->currency_symbol,
            ],
            'plans' => [
                'basic' => [
                    'id' => 'basic',
                    'name' => 'Basic',
                    'prices' => $this->getPlanPrices('basic'),
                ],
                'premium' => [
                    'id' => 'premium',
                    'name' => 'Premium',
                    'prices' => $this->getPlanPrices('premium'),
                ],
                'platinum' => [
                    'id' => 'platinum',
                    'name' => 'Platinum',
                    'prices' => $this->getPlanPrices('platinum'),
                ],
            ],
            'discounts' => [
                'quarterly' => (float) $this->quarterly_discount,
                'yearly' => (float) $this->yearly_discount,
            ],
            'tax' => [
                'rate' => (float) $this->tax_rate,
                'name' => $this->tax_name,
                'inclusive' => $this->tax_inclusive,
            ],
            'payment_methods' => $this->payment_methods ?? [],
        ];
    }
}
