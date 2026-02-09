<?php

namespace App\Services;

use App\Models\CountryPricingConfig;
use Illuminate\Support\Facades\Cache;

class PricingService
{
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private GeolocationService $geolocationService,
        private ExchangeRateService $exchangeRateService
    ) {}

    /**
     * Get subscription plans for a specific country
     */
    public function getPlansForCountry(string $countryCode): array
    {
        $cacheKey = "pricing:plans:{$countryCode}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($countryCode) {
            $config = CountryPricingConfig::getByCountry($countryCode);

            if (!$config) {
                // Fall back to default pricing (US)
                $config = CountryPricingConfig::getDefault();
            }

            if (!$config) {
                return $this->getHardcodedPlans($countryCode);
            }

            return $this->formatPlansResponse($config);
        });
    }

    /**
     * Format plans response from config
     */
    private function formatPlansResponse(CountryPricingConfig $config): array
    {
        $plans = [
            'free' => [
                'id' => 'free',
                'name' => 'Free',
                'description' => 'Get started with basic features',
                'prices' => [
                    'monthly' => 0,
                    'quarterly' => 0,
                    'yearly' => 0,
                ],
                'features' => [
                    'View limited profiles',
                    'Basic search filters',
                    'Send 5 interests per day',
                    'View who liked you (blurred)',
                ],
                'limitations' => [
                    'Limited profile views',
                    'Basic search only',
                    'No chat access',
                ],
                'popular' => false,
            ],
            'basic' => [
                'id' => 'basic',
                'name' => 'Basic',
                'description' => 'Perfect for getting started',
                'prices' => $config->getPlanPrices('basic'),
                'features' => [
                    'Unlimited profile views',
                    'Advanced search filters',
                    'Send unlimited interests',
                    'Chat with matches',
                    'View who liked you',
                    'Profile highlighting',
                ],
                'limitations' => [
                    'No video calls',
                    'No priority support',
                ],
                'popular' => false,
            ],
            'premium' => [
                'id' => 'premium',
                'name' => 'Premium',
                'description' => 'Most popular choice',
                'prices' => $config->getPlanPrices('premium'),
                'features' => [
                    'All Basic features',
                    'Video calls with matches',
                    'See who viewed your profile',
                    'Advanced matching algorithm',
                    'Priority in search results',
                    'Read receipts in chat',
                    'Profile verification badge',
                ],
                'limitations' => [],
                'popular' => true,
            ],
            'platinum' => [
                'id' => 'platinum',
                'name' => 'Platinum',
                'description' => 'Premium experience',
                'prices' => $config->getPlanPrices('platinum'),
                'features' => [
                    'All Premium features',
                    'Dedicated matchmaker support',
                    'VIP badge on profile',
                    'First access to new features',
                    'Incognito browsing mode',
                    'Super boost (1 per week)',
                    'Priority customer support',
                    'Horoscope compatibility analysis',
                ],
                'limitations' => [],
                'popular' => false,
            ],
        ];

        // Add 'type' field to each plan and convert to indexed array
        $plansArray = [];
        foreach ($plans as $type => $plan) {
            $plan['type'] = $type;
            $plansArray[] = $plan;
        }

        return [
            'country_code' => $config->country_code,
            'country_name' => $config->country_name,
            'currency_code' => $config->currency_code,
            'currency_symbol' => $config->currency_symbol,
            'plans' => $plansArray,
            'discounts' => [
                'quarterly' => (float) $config->quarterly_discount,
                'yearly' => (float) $config->yearly_discount,
            ],
            'tax_rate' => (float) $config->tax_rate,
            'tax_name' => $config->tax_name,
            'tax_inclusive' => $config->tax_inclusive,
            'payment_methods' => $config->payment_methods ?? ['stripe', 'paypal'],
        ];
    }

    /**
     * Calculate price for a specific plan, duration, and country
     */
    public function calculatePrice(
        string $plan,
        string $duration,
        string $countryCode,
        ?string $discountCode = null
    ): array {
        $config = CountryPricingConfig::getByCountry($countryCode)
            ?? CountryPricingConfig::getDefault();

        if (!$config) {
            return $this->getHardcodedPrice($plan, $duration, $countryCode);
        }

        $basePrice = $config->getPrice($plan, $duration);
        $priceWithTax = $config->getPriceWithTax($plan, $duration);

        // Apply discount code if provided
        $discountAmount = 0;
        if ($discountCode) {
            $discountAmount = $this->applyDiscount($basePrice, $discountCode);
        }

        $finalPrice = $priceWithTax - $discountAmount;

        // Convert to USD for internal tracking
        $priceUSD = $this->convertToUSD($finalPrice, $config->currency_code);

        return [
            'base_price' => $basePrice,
            'tax_amount' => $priceWithTax - $basePrice,
            'tax_rate' => (float) $config->tax_rate,
            'tax_name' => $config->tax_name,
            'discount_amount' => $discountAmount,
            'discount_code' => $discountCode,
            'final_price' => $finalPrice,
            'final_price_formatted' => $config->currency_symbol . number_format($finalPrice, 2),
            'currency_code' => $config->currency_code,
            'currency_symbol' => $config->currency_symbol,
            'price_usd' => $priceUSD,
            'plan' => $plan,
            'duration' => $duration,
            'country_code' => $countryCode,
        ];
    }

    /**
     * Get available payment methods for a country
     */
    public function getAvailablePaymentMethods(string $countryCode): array
    {
        $config = CountryPricingConfig::getByCountry($countryCode);

        if ($config && $config->payment_methods) {
            return $config->payment_methods;
        }

        // Default payment methods by region
        $lkMethods = ['payhere', 'webxpay', 'stripe'];

        if (in_array($countryCode, ['LK'])) {
            return $lkMethods;
        }

        return ['stripe', 'paypal'];
    }

    /**
     * Convert amount to USD
     */
    public function convertToUSD(float $amount, string $fromCurrency): float
    {
        if ($fromCurrency === 'USD') {
            return $amount;
        }

        return $this->exchangeRateService->convert($amount, $fromCurrency, 'USD');
    }

    /**
     * Convert amount from USD to target currency
     */
    public function convertFromUSD(float $amount, string $toCurrency): float
    {
        if ($toCurrency === 'USD') {
            return $amount;
        }

        return $this->exchangeRateService->convert($amount, 'USD', $toCurrency);
    }

    /**
     * Apply discount code to price
     */
    public function applyDiscount(float $price, string $discountCode): float
    {
        // TODO: Implement discount code logic with DiscountCode model
        // For now, return 0 (no discount)
        $discounts = [
            'WELCOME10' => 0.10, // 10% off
            'PREMIUM20' => 0.20, // 20% off
            'SPECIAL25' => 0.25, // 25% off
        ];

        $code = strtoupper($discountCode);
        if (isset($discounts[$code])) {
            return $price * $discounts[$code];
        }

        return 0;
    }

    /**
     * Get all supported countries with pricing
     */
    public function getSupportedCountries(): array
    {
        return Cache::remember('pricing:countries', self::CACHE_TTL, function () {
            return CountryPricingConfig::active()
                ->ordered()
                ->get()
                ->map(fn($config) => [
                    'country_code' => $config->country_code,
                    'country_name' => $config->country_name,
                    'currency_code' => $config->currency_code,
                    'currency_symbol' => $config->currency_symbol,
                    'starting_price' => $config->basic_monthly,
                    'is_default' => $config->is_default,
                ])
                ->toArray();
        });
    }

    /**
     * Clear pricing cache
     */
    public function clearCache(?string $countryCode = null): void
    {
        if ($countryCode) {
            Cache::forget("pricing:plans:{$countryCode}");
        } else {
            // Clear all pricing cache
            Cache::forget('pricing:countries');
            $configs = CountryPricingConfig::all();
            foreach ($configs as $config) {
                Cache::forget("pricing:plans:{$config->country_code}");
            }
        }
    }

    /**
     * Hardcoded fallback plans (if database is empty)
     */
    private function getHardcodedPlans(string $countryCode): array
    {
        $currency = $this->geolocationService->getCurrencyForCountry($countryCode);
        $symbol = $this->geolocationService->getCurrencySymbol($currency);

        // Base USD prices
        $basePrices = [
            'basic' => ['monthly' => 4.99, 'quarterly' => 13.47, 'yearly' => 47.90],
            'premium' => ['monthly' => 9.99, 'quarterly' => 26.97, 'yearly' => 95.90],
            'platinum' => ['monthly' => 19.99, 'quarterly' => 53.97, 'yearly' => 191.90],
        ];

        // Convert if not USD
        if ($currency !== 'USD') {
            foreach ($basePrices as $plan => $prices) {
                foreach ($prices as $duration => $price) {
                    $basePrices[$plan][$duration] = round($this->convertFromUSD($price, $currency), 2);
                }
            }
        }

        // Build plans as indexed array with type field
        $plans = [
            [
                'id' => 'free',
                'type' => 'free',
                'name' => 'Free',
                'prices' => ['monthly' => 0, 'quarterly' => 0, 'yearly' => 0],
                'features' => ['View limited profiles', 'Basic search'],
                'popular' => false,
            ],
            [
                'id' => 'basic',
                'type' => 'basic',
                'name' => 'Basic',
                'prices' => $basePrices['basic'],
                'features' => ['Unlimited views', 'Advanced search', 'Chat'],
                'popular' => false,
            ],
            [
                'id' => 'premium',
                'type' => 'premium',
                'name' => 'Premium',
                'prices' => $basePrices['premium'],
                'features' => ['All Basic features', 'Video calls', 'Priority'],
                'popular' => true,
            ],
            [
                'id' => 'platinum',
                'type' => 'platinum',
                'name' => 'Platinum',
                'prices' => $basePrices['platinum'],
                'features' => ['All Premium features', 'VIP support', 'Matchmaker'],
                'popular' => false,
            ],
        ];

        return [
            'country_code' => $countryCode,
            'country_name' => $countryCode, // Would need country name lookup
            'currency_code' => $currency,
            'currency_symbol' => $symbol,
            'plans' => $plans,
            'discounts' => ['quarterly' => 10, 'yearly' => 20],
            'tax_rate' => 0,
            'tax_name' => null,
            'tax_inclusive' => false,
            'payment_methods' => ['stripe', 'paypal'],
        ];
    }

    /**
     * Hardcoded fallback price calculation
     */
    private function getHardcodedPrice(string $plan, string $duration, string $countryCode): array
    {
        $basePricesUSD = [
            'basic' => ['monthly' => 4.99, 'quarterly' => 13.47, 'yearly' => 47.90],
            'premium' => ['monthly' => 9.99, 'quarterly' => 26.97, 'yearly' => 95.90],
            'platinum' => ['monthly' => 19.99, 'quarterly' => 53.97, 'yearly' => 191.90],
        ];

        $currency = $this->geolocationService->getCurrencyForCountry($countryCode);
        $symbol = $this->geolocationService->getCurrencySymbol($currency);

        $priceUSD = $basePricesUSD[$plan][$duration] ?? 0;
        $price = $currency === 'USD' ? $priceUSD : $this->convertFromUSD($priceUSD, $currency);

        return [
            'base_price' => round($price, 2),
            'tax_amount' => 0,
            'tax_rate' => 0,
            'tax_name' => null,
            'discount_amount' => 0,
            'discount_code' => null,
            'final_price' => round($price, 2),
            'final_price_formatted' => $symbol . number_format($price, 2),
            'currency_code' => $currency,
            'currency_symbol' => $symbol,
            'price_usd' => $priceUSD,
            'plan' => $plan,
            'duration' => $duration,
            'country_code' => $countryCode,
        ];
    }
}
