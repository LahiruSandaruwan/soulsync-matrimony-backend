<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stevebauman\Location\Facades\Location;

class GeolocationService
{
    /**
     * Country to currency mapping
     */
    private array $countryCurrencyMap = [
        'US' => 'USD',
        'LK' => 'LKR',
        'IN' => 'INR',
        'GB' => 'GBP',
        'DE' => 'EUR',
        'FR' => 'EUR',
        'IT' => 'EUR',
        'ES' => 'EUR',
        'NL' => 'EUR',
        'BE' => 'EUR',
        'AT' => 'EUR',
        'IE' => 'EUR',
        'PT' => 'EUR',
        'FI' => 'EUR',
        'GR' => 'EUR',
        'AU' => 'AUD',
        'CA' => 'CAD',
        'SG' => 'SGD',
        'AE' => 'AED',
        'SA' => 'SAR',
        'MY' => 'MYR',
        'JP' => 'JPY',
        'KR' => 'KRW',
        'CN' => 'CNY',
        'HK' => 'HKD',
        'NZ' => 'NZD',
        'CH' => 'CHF',
        'SE' => 'SEK',
        'NO' => 'NOK',
        'DK' => 'DKK',
        'PK' => 'PKR',
        'BD' => 'BDT',
        'NP' => 'NPR',
    ];

    /**
     * Currency symbols
     */
    private array $currencySymbols = [
        'USD' => '$',
        'LKR' => 'Rs.',
        'INR' => '₹',
        'GBP' => '£',
        'EUR' => '€',
        'AUD' => 'A$',
        'CAD' => 'C$',
        'SGD' => 'S$',
        'AED' => 'د.إ',
        'SAR' => '﷼',
        'MYR' => 'RM',
        'JPY' => '¥',
        'KRW' => '₩',
        'CNY' => '¥',
        'HKD' => 'HK$',
        'NZD' => 'NZ$',
        'CHF' => 'CHF',
        'SEK' => 'kr',
        'NOK' => 'kr',
        'DKK' => 'kr',
        'PKR' => 'Rs.',
        'BDT' => '৳',
        'NPR' => 'रू',
    ];

    /**
     * Detect country from IP address
     *
     * @param string|null $ip
     * @return array
     */
    public function detectCountry(?string $ip = null): array
    {
        $ip = $ip ?? request()->ip();

        // Check cache first (1 hour TTL)
        $cacheKey = 'geolocation:' . md5($ip);

        return Cache::remember($cacheKey, 3600, function () use ($ip) {
            try {
                // Handle localhost/private IPs
                if ($this->isPrivateIP($ip)) {
                    return $this->getDefaultLocation();
                }

                $position = Location::get($ip);

                if ($position) {
                    return [
                        'success' => true,
                        'country_code' => $position->countryCode ?? 'US',
                        'country_name' => $position->countryName ?? 'United States',
                        'region' => $position->regionName ?? null,
                        'city' => $position->cityName ?? null,
                        'timezone' => $position->timezone ?? 'UTC',
                        'currency' => $this->getCurrencyForCountry($position->countryCode ?? 'US'),
                        'currency_symbol' => $this->getCurrencySymbol($this->getCurrencyForCountry($position->countryCode ?? 'US')),
                        'ip' => $ip,
                        'source' => 'geolocation',
                    ];
                }

                return $this->getDefaultLocation();

            } catch (\Exception $e) {
                Log::warning('Geolocation failed for IP: ' . $ip, [
                    'error' => $e->getMessage()
                ]);
                return $this->getDefaultLocation();
            }
        });
    }

    /**
     * Get country code from IP
     *
     * @param string $ip
     * @return string|null
     */
    public function getCountryFromIP(string $ip): ?string
    {
        $location = $this->detectCountry($ip);
        return $location['country_code'] ?? null;
    }

    /**
     * Get currency for a country code
     *
     * @param string $countryCode
     * @return string
     */
    public function getCurrencyForCountry(string $countryCode): string
    {
        return $this->countryCurrencyMap[strtoupper($countryCode)] ?? 'USD';
    }

    /**
     * Get currency symbol
     *
     * @param string $currencyCode
     * @return string
     */
    public function getCurrencySymbol(string $currencyCode): string
    {
        return $this->currencySymbols[strtoupper($currencyCode)] ?? $currencyCode;
    }

    /**
     * Get all supported countries with their currencies
     *
     * @return array
     */
    public function getSupportedCountries(): array
    {
        $countries = [];
        foreach ($this->countryCurrencyMap as $countryCode => $currency) {
            $countries[] = [
                'country_code' => $countryCode,
                'currency_code' => $currency,
                'currency_symbol' => $this->getCurrencySymbol($currency),
            ];
        }
        return $countries;
    }

    /**
     * Check if IP is private/localhost
     *
     * @param string $ip
     * @return bool
     */
    private function isPrivateIP(string $ip): bool
    {
        if ($ip === '127.0.0.1' || $ip === '::1' || $ip === 'localhost') {
            return true;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    /**
     * Get default location (US)
     *
     * @return array
     */
    private function getDefaultLocation(): array
    {
        return [
            'success' => true,
            'country_code' => 'US',
            'country_name' => 'United States',
            'region' => null,
            'city' => null,
            'timezone' => 'America/New_York',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'ip' => request()->ip(),
            'source' => 'default',
        ];
    }

    /**
     * Clear geolocation cache for an IP
     *
     * @param string $ip
     * @return bool
     */
    public function clearCache(string $ip): bool
    {
        $cacheKey = 'geolocation:' . md5($ip);
        return Cache::forget($cacheKey);
    }
}
