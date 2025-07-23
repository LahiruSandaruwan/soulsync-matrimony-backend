<?php

namespace App\Console\Commands;

use App\Models\ExchangeRate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateExchangeRatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rates:update 
                            {--currency= : Update specific currency only (e.g., LKR)}
                            {--force : Force update even if rates are recent}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update exchange rates from external API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting exchange rate update...');

        $specificCurrency = $this->option('currency');
        $force = $this->option('force');

        try {
            if ($specificCurrency) {
                $this->updateSpecificCurrency($specificCurrency, $force);
            } else {
                $this->updateAllCurrencies($force);
            }

            $this->info('Exchange rate update completed successfully.');

        } catch (\Exception $e) {
            $this->error('Failed to update exchange rates: ' . $e->getMessage());
            Log::error('Exchange rate update command failed', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Update exchange rate for a specific currency.
     */
    private function updateSpecificCurrency(string $currency, bool $force): void
    {
        $this->info("Updating exchange rate for {$currency}...");

        try {
            $rate = ExchangeRate::fetchAndStoreRate('USD', $currency);
            
            if ($rate) {
                $this->info("✓ Updated USD to {$currency}: {$rate}");
            } else {
                $this->warn("⚠ Failed to update rate for {$currency}");
            }

        } catch (\Exception $e) {
            $this->error("✗ Error updating {$currency}: {$e->getMessage()}");
            Log::error("Failed to update exchange rate for {$currency}", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Update exchange rates for all supported currencies.
     */
    private function updateAllCurrencies(bool $force): void
    {
        $currencies = $this->getSupportedCurrencies();
        $progressBar = $this->output->createProgressBar(count($currencies));
        $progressBar->start();

        $successCount = 0;
        $failedCount = 0;

        foreach ($currencies as $currency) {
            try {
                // Check if rate needs updating (unless forced)
                if (!$force && !$this->needsUpdate($currency)) {
                    $progressBar->advance();
                    continue;
                }

                $rate = ExchangeRate::fetchAndStoreRate('USD', $currency);
                
                if ($rate) {
                    $successCount++;
                } else {
                    $failedCount++;
                    Log::warning("Failed to fetch rate for {$currency}");
                }

                // Small delay to respect API rate limits
                usleep(200000); // 200ms delay

            } catch (\Exception $e) {
                $failedCount++;
                Log::error("Failed to update exchange rate for {$currency}", [
                    'currency' => $currency,
                    'error' => $e->getMessage()
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("Exchange rate update summary:");
        $this->info("✓ Successfully updated: {$successCount}");
        
        if ($failedCount > 0) {
            $this->warn("⚠ Failed to update: {$failedCount}");
        }

        // Show current rates
        $this->showCurrentRates();
    }

    /**
     * Check if a currency rate needs updating.
     */
    private function needsUpdate(string $currency): bool
    {
        $existingRate = ExchangeRate::where('from_currency', 'USD')
            ->where('to_currency', $currency)
            ->where('is_active', true)
            ->orderBy('last_updated_at', 'desc')
            ->first();

        if (!$existingRate) {
            return true; // No rate exists, needs update
        }

        // Check if rate is older than the update frequency
        $minutesSinceUpdate = $existingRate->last_updated_at->diffInMinutes(now());
        $updateFrequency = $existingRate->update_frequency_minutes ?? 60;

        return $minutesSinceUpdate >= $updateFrequency;
    }

    /**
     * Get list of supported currencies for exchange rate updates.
     */
    private function getSupportedCurrencies(): array
    {
        return [
            'LKR', // Sri Lankan Rupee (primary)
            'INR', // Indian Rupee
            'EUR', // Euro
            'GBP', // British Pound
            'AUD', // Australian Dollar
            'CAD', // Canadian Dollar
            'SGD', // Singapore Dollar
            'AED', // UAE Dirham
            'SAR', // Saudi Riyal
            'JPY', // Japanese Yen
            'CNY', // Chinese Yuan
            'MYR', // Malaysian Ringgit
            'THB', // Thai Baht
            'PHP', // Philippine Peso
        ];
    }

    /**
     * Display current exchange rates.
     */
    private function showCurrentRates(): void
    {
        $this->newLine();
        $this->info('Current Exchange Rates (USD base):');
        $this->newLine();

        $rates = ExchangeRate::fromUSD()
            ->active()
            ->orderBy('to_currency')
            ->get(['to_currency', 'rate', 'last_updated_at', 'confidence_score']);

        if ($rates->isEmpty()) {
            $this->warn('No exchange rates found.');
            return;
        }

        $headers = ['Currency', 'Rate', 'Last Updated', 'Confidence'];
        $rows = [];

        foreach ($rates as $rate) {
            $rows[] = [
                "USD → {$rate->to_currency}",
                number_format($rate->rate, 4),
                $rate->last_updated_at ? $rate->last_updated_at->diffForHumans() : 'Never',
                $rate->confidence_score . '%'
            ];
        }

        $this->table($headers, $rows);
    }
}
