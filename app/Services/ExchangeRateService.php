<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class ExchangeRateService
{
    private string $apiKey;
    private string $baseUrl = 'https://v6.exchangerate-api.com/v6/';
    private int $cacheDuration = 1800; // 30 minutes

    public function __construct()
    {
        $this->apiKey = config('services.exchange_rate.api_key');
    }

    /**
     * Get exchange rate between two currencies
     */
    public function getExchangeRate(string $from, string $to): ?float
    {
        // Return 1.0 for same currency
        if ($from === $to) {
            return 1.0;
        }

        // Try cache first
        $cacheKey = "exchange_rate_{$from}_{$to}";
        $cachedRate = Cache::get($cacheKey);
        
        if ($cachedRate !== null) {
            return (float) $cachedRate;
        }

        // Try database
        $rate = $this->getRateFromDatabase($from, $to);
        if ($rate !== null) {
            Cache::put($cacheKey, $rate, $this->cacheDuration);
            return $rate;
        }

        // Fetch from API
        $rate = $this->fetchFromAPI($from, $to);
        if ($rate !== null) {
            Cache::put($cacheKey, $rate, $this->cacheDuration);
            return $rate;
        }

        // Fallback to static rates
        return $this->getFallbackRate($from, $to);
    }

    /**
     * Get rate from database
     */
    private function getRateFromDatabase(string $from, string $to): ?float
    {
        $exchangeRate = ExchangeRate::where('from_currency', $from)
            ->where('to_currency', $to)
            ->where('is_active', true)
            ->where(function($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->orderBy('effective_date', 'desc')
            ->first();

        if ($exchangeRate) {
            $exchangeRate->recordUsage();
            return (float) $exchangeRate->rate;
        }

        return null;
    }

    /**
     * Fetch rate from external API
     */
    private function fetchFromAPI(string $from, string $to): ?float
    {
        if (!$this->apiKey) {
            Log::warning('Exchange rate API key not configured');
            return null;
        }

        try {
            $url = $this->baseUrl . $this->apiKey . "/pair/{$from}/{$to}";
            
            $response = Http::timeout(10)->get($url);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['result'] === 'success') {
                    $rate = $data['conversion_rate'];
                    
                    // Store in database
                    $this->storeRate($from, $to, $rate, $data);
                    
                    return (float) $rate;
                }
            }
            
            Log::error('Exchange rate API error', [
                'from' => $from,
                'to' => $to,
                'response' => $response->body()
            ]);
            
        } catch (Exception $e) {
            Log::error('Exchange rate fetch failed', [
                'from' => $from,
                'to' => $to,
                'error' => $e->getMessage()
            ]);
        }
        
        return null;
    }

    /**
     * Store rate in database
     */
    private function storeRate(string $from, string $to, float $rate, array $apiResponse = []): void
    {
        $data = [
            'from_currency' => $from,
            'to_currency' => $to,
            'rate' => $rate,
            'inverse_rate' => 1 / $rate,
            'source' => 'api',
            'provider' => 'exchangerate-api.com',
            'effective_date' => now(),
            'expires_at' => now()->addHours(6),
            'last_updated_at' => now(),
            'confidence_score' => 100,
            'api_response' => $apiResponse,
            'api_request_id' => uniqid()
        ];

        // Check if rate already exists for today
        $existing = ExchangeRate::where('from_currency', $from)
            ->where('to_currency', $to)
            ->whereDate('effective_date', now()->toDateString())
            ->first();

        if ($existing) {
            $data['previous_rate'] = $existing->rate;
            $data['change_amount'] = $rate - $existing->rate;
            $data['change_percentage'] = (($rate - $existing->rate) / $existing->rate) * 100;
            $data['trend'] = $data['change_amount'] > 0 ? 'up' : ($data['change_amount'] < 0 ? 'down' : 'stable');
            
            $existing->update($data);
        } else {
            ExchangeRate::create($data);
        }
    }

    /**
     * Get fallback exchange rates
     */
    private function getFallbackRate(string $from, string $to): ?float
    {
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
            'LKR_USD' => 0.0033,
            'INR_USD' => 0.012,
            'EUR_USD' => 1.09,
            'GBP_USD' => 1.27,
            'AUD_USD' => 0.66,
            'CAD_USD' => 0.74,
            'SGD_USD' => 0.75,
            'AED_USD' => 0.27,
            'SAR_USD' => 0.27,
        ];
        
        $rateKey = "{$from}_{$to}";
        
        if (isset($fallbackRates[$rateKey])) {
            $rate = $fallbackRates[$rateKey];
            
            // Store fallback rate
            $this->storeRate($from, $to, $rate, ['source' => 'fallback']);
            
            return (float) $rate;
        }
        
        Log::error('No exchange rate available', [
            'from' => $from,
            'to' => $to
        ]);
        
        return null;
    }

    /**
     * Convert amount from one currency to another
     */
    public function convert(float $amount, string $from, string $to): ?float
    {
        $rate = $this->getExchangeRate($from, $to);
        
        if ($rate === null) {
            return null;
        }
        
        return round($amount * $rate, 2);
    }

    /**
     * Get USD equivalent of amount in given currency
     */
    public function toUSD(float $amount, string $fromCurrency): ?float
    {
        if ($fromCurrency === 'USD') {
            return $amount;
        }
        
        return $this->convert($amount, $fromCurrency, 'USD');
    }

    /**
     * Convert USD amount to specified currency
     */
    public function fromUSD(float $usdAmount, string $toCurrency): ?float
    {
        if ($toCurrency === 'USD') {
            return $usdAmount;
        }
        
        return $this->convert($usdAmount, 'USD', $toCurrency);
    }

    /**
     * Update all active exchange rates
     */
    public function updateAllRates(): void
    {
        $currencies = ['LKR', 'INR', 'EUR', 'GBP', 'AUD', 'CAD', 'SGD', 'AED', 'SAR'];
        
        foreach ($currencies as $currency) {
            $this->fetchFromAPI('USD', $currency);
            
            // Small delay to avoid API rate limits
            usleep(200000); // 200ms delay
        }
    }

    /**
     * Get popular currency pairs
     */
    public function getPopularRates(): array
    {
        $pairs = [
            ['USD', 'LKR'],
            ['USD', 'INR'],
            ['USD', 'EUR'],
            ['USD', 'GBP'],
            ['USD', 'AUD'],
            ['USD', 'CAD'],
        ];
        
        $rates = [];
        
        foreach ($pairs as $pair) {
            $rate = $this->getExchangeRate($pair[0], $pair[1]);
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

    /**
     * Clear cache for specific currency pair
     */
    public function clearCache(string $from, string $to): void
    {
        $cacheKey = "exchange_rate_{$from}_{$to}";
        Cache::forget($cacheKey);
    }

    /**
     * Clear all exchange rate cache
     */
    public function clearAllCache(): void
    {
        // This is a simplified approach - in production you might want to use cache tags
        $keys = Cache::get('exchange_rate_keys', []);
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        Cache::forget('exchange_rate_keys');
    }
}
