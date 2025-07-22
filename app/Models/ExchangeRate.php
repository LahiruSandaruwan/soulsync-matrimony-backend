<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ExchangeRate extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'from_currency', 'to_currency', 'rate', 'inverse_rate',
        'source', 'provider', 'bid_rate', 'ask_rate', 'mid_rate',
        'effective_date', 'expires_at', 'is_active', 'is_cached',
        'volatility', 'confidence_score', 'last_updated_at', 'update_frequency_minutes',
        'previous_rate', 'change_amount', 'change_percentage', 'trend',
        'usage_count', 'last_used_at', 'api_response', 'api_request_id'
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:6',
            'inverse_rate' => 'decimal:6',
            'bid_rate' => 'decimal:6',
            'ask_rate' => 'decimal:6',
            'mid_rate' => 'decimal:6',
            'previous_rate' => 'decimal:6',
            'change_amount' => 'decimal:6',
            'change_percentage' => 'decimal:4',
            'volatility' => 'decimal:4',
            'effective_date' => 'datetime',
            'expires_at' => 'datetime',
            'last_updated_at' => 'datetime',
            'last_used_at' => 'datetime',
            'is_active' => 'boolean',
            'is_cached' => 'boolean',
            'api_response' => 'array',
        ];
    }

    // Static methods for currency conversion

    /**
     * Get current exchange rate between two currencies.
     */
    public static function getRate(string $fromCurrency, string $toCurrency): ?float
    {
        // Return 1.0 for same currency
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        // Try to get cached rate first
        $cacheKey = "exchange_rate_{$fromCurrency}_{$toCurrency}";
        $cachedRate = Cache::get($cacheKey);
        
        if ($cachedRate) {
            return (float) $cachedRate;
        }

        // Get from database
        $exchangeRate = self::where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->where('is_active', true)
            ->where(function($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->orderBy('effective_date', 'desc')
            ->first();

        if ($exchangeRate) {
            // Record usage
            $exchangeRate->recordUsage();
            
            // Cache for performance
            Cache::put($cacheKey, $exchangeRate->rate, now()->addMinutes(30));
            
            return (float) $exchangeRate->rate;
        }

        // Try to fetch fresh rate if not found
        return self::fetchAndStoreRate($fromCurrency, $toCurrency);
    }

    /**
     * Convert amount from one currency to another.
     */
    public static function convert(float $amount, string $fromCurrency, string $toCurrency): ?float
    {
        $rate = self::getRate($fromCurrency, $toCurrency);
        
        if ($rate === null) {
            return null;
        }
        
        return round($amount * $rate, 2);
    }

    /**
     * Get USD equivalent of amount in given currency.
     */
    public static function toUSD(float $amount, string $fromCurrency): ?float
    {
        if ($fromCurrency === 'USD') {
            return $amount;
        }
        
        return self::convert($amount, $fromCurrency, 'USD');
    }

    /**
     * Convert USD amount to specified currency.
     */
    public static function fromUSD(float $usdAmount, string $toCurrency): ?float
    {
        if ($toCurrency === 'USD') {
            return $usdAmount;
        }
        
        return self::convert($usdAmount, 'USD', $toCurrency);
    }

    /**
     * Fetch fresh exchange rate from external API.
     */
    public static function fetchAndStoreRate(string $fromCurrency, string $toCurrency): ?float
    {
        try {
            // Use exchangerate-api.com (free tier available)
            $apiKey = config('services.exchange_rate.api_key');
            
            if (!$apiKey) {
                Log::warning('Exchange rate API key not configured');
                return self::getFallbackRate($fromCurrency, $toCurrency);
            }

            $url = "https://v6.exchangerate-api.com/v6/{$apiKey}/pair/{$fromCurrency}/{$toCurrency}";
            
            $response = Http::timeout(10)->get($url);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['result'] === 'success') {
                    $rate = $data['conversion_rate'];
                    
                    // Store in database
                    self::storeRate([
                        'from_currency' => $fromCurrency,
                        'to_currency' => $toCurrency,
                        'rate' => $rate,
                        'inverse_rate' => 1 / $rate,
                        'source' => 'api',
                        'provider' => 'exchangerate-api.com',
                        'effective_date' => now(),
                        'expires_at' => now()->addHours(6), // Expire in 6 hours
                        'last_updated_at' => now(),
                        'confidence_score' => 100,
                        'api_response' => $data,
                        'api_request_id' => uniqid()
                    ]);
                    
                    return (float) $rate;
                }
            }
            
            Log::error('Exchange rate API error', [
                'from' => $fromCurrency,
                'to' => $toCurrency,
                'response' => $response->body()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Exchange rate fetch failed', [
                'from' => $fromCurrency,
                'to' => $toCurrency,
                'error' => $e->getMessage()
            ]);
        }
        
        // Fall back to static rates
        return self::getFallbackRate($fromCurrency, $toCurrency);
    }

    /**
     * Store exchange rate in database.
     */
    private static function storeRate(array $data): void
    {
        // Check if rate already exists for today
        $existing = self::where('from_currency', $data['from_currency'])
            ->where('to_currency', $data['to_currency'])
            ->whereDate('effective_date', now()->toDateString())
            ->first();

        if ($existing) {
            // Update existing rate
            $data['previous_rate'] = $existing->rate;
            $data['change_amount'] = $data['rate'] - $existing->rate;
            $data['change_percentage'] = (($data['rate'] - $existing->rate) / $existing->rate) * 100;
            $data['trend'] = $data['change_amount'] > 0 ? 'up' : ($data['change_amount'] < 0 ? 'down' : 'stable');
            
            $existing->update($data);
        } else {
            // Create new rate record
            self::create($data);
        }
    }

    /**
     * Get fallback exchange rates (hardcoded as last resort).
     */
    private static function getFallbackRate(string $fromCurrency, string $toCurrency): ?float
    {
        // Hardcoded fallback rates (should be updated periodically)
        $fallbackRates = [
            'USD_LKR' => 300.0,
            'USD_INR' => 83.0,
            'USD_EUR' => 0.92,
            'USD_GBP' => 0.79,
            'USD_AUD' => 1.52,
            'USD_CAD' => 1.36,
            'USD_SGD' => 1.34,
            'USD_AED' => 3.67,
            'USD_SAR' => 3.75,
        ];
        
        $rateKey = "{$fromCurrency}_{$toCurrency}";
        $inverseKey = "{$toCurrency}_{$fromCurrency}";
        
        if (isset($fallbackRates[$rateKey])) {
            $rate = $fallbackRates[$rateKey];
            
            // Store fallback rate
            self::storeRate([
                'from_currency' => $fromCurrency,
                'to_currency' => $toCurrency,
                'rate' => $rate,
                'inverse_rate' => 1 / $rate,
                'source' => 'fallback',
                'provider' => 'system_default',
                'effective_date' => now(),
                'expires_at' => now()->addDays(1),
                'last_updated_at' => now(),
                'confidence_score' => 60, // Lower confidence for fallback
            ]);
            
            return (float) $rate;
        }
        
        if (isset($fallbackRates[$inverseKey])) {
            $rate = 1 / $fallbackRates[$inverseKey];
            
            self::storeRate([
                'from_currency' => $fromCurrency,
                'to_currency' => $toCurrency,
                'rate' => $rate,
                'inverse_rate' => $fallbackRates[$inverseKey],
                'source' => 'fallback',
                'provider' => 'system_default',
                'effective_date' => now(),
                'expires_at' => now()->addDays(1),
                'last_updated_at' => now(),
                'confidence_score' => 60,
            ]);
            
            return (float) $rate;
        }
        
        Log::error('No exchange rate available', [
            'from' => $fromCurrency,
            'to' => $toCurrency
        ]);
        
        return null;
    }

    /**
     * Update all active exchange rates.
     */
    public static function updateAllRates(): void
    {
        $currencies = ['LKR', 'INR', 'EUR', 'GBP', 'AUD', 'CAD', 'SGD', 'AED', 'SAR'];
        
        foreach ($currencies as $currency) {
            self::fetchAndStoreRate('USD', $currency);
            
            // Small delay to avoid API rate limits
            usleep(200000); // 200ms delay
        }
    }

    /**
     * Get popular currency pairs for the application.
     */
    public static function getPopularRates(): array
    {
        $pairs = [
            ['USD', 'LKR'], // Primary for Sri Lankan users
            ['USD', 'INR'], // Indian users
            ['USD', 'EUR'], // European users
            ['USD', 'GBP'], // UK users
            ['USD', 'AUD'], // Australian users
            ['USD', 'CAD'], // Canadian users
        ];
        
        $rates = [];
        
        foreach ($pairs as $pair) {
            $rate = self::getRate($pair[0], $pair[1]);
            if ($rate) {
                $rates["{$pair[0]}_{$pair[1]}"] = [
                    'from' => $pair[0],
                    'to' => $pair[1],
                    'rate' => $rate,
                    'formatted' => number_format($rate, 2)
                ];
            }
        }
        
        return $rates;
    }

    // Instance methods

    /**
     * Record usage of this exchange rate.
     */
    public function recordUsage(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Check if rate needs updating.
     */
    public function needsUpdate(): bool
    {
        if (!$this->last_updated_at) {
            return true;
        }
        
        $minutesSinceUpdate = $this->last_updated_at->diffInMinutes(now());
        return $minutesSinceUpdate >= $this->update_frequency_minutes;
    }

    /**
     * Get rate age in human readable format.
     */
    public function getAgeAttribute(): string
    {
        if (!$this->last_updated_at) {
            return 'Unknown';
        }
        
        return $this->last_updated_at->diffForHumans();
    }

    /**
     * Get trend indicator.
     */
    public function getTrendIconAttribute(): string
    {
        return match($this->trend) {
            'up' => '↗️',
            'down' => '↘️',
            'stable' => '➡️',
            default => '❓'
        };
    }

    /**
     * Format rate for display.
     */
    public function getFormattedRateAttribute(): string
    {
        return number_format($this->rate, 4);
    }

    // Scopes

    /**
     * Scope for active rates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where(function($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    /**
     * Scope for rates from USD.
     */
    public function scopeFromUSD($query)
    {
        return $query->where('from_currency', 'USD');
    }

    /**
     * Scope for rates to USD.
     */
    public function scopeToUSD($query)
    {
        return $query->where('to_currency', 'USD');
    }

    /**
     * Scope for recent rates.
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('last_updated_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope for high confidence rates.
     */
    public function scopeHighConfidence($query, int $minScore = 80)
    {
        return $query->where('confidence_score', '>=', $minScore);
    }
}
