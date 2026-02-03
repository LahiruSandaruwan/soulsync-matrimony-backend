<?php

namespace Database\Seeders;

use App\Models\CountryPricingConfig;
use Illuminate\Database\Seeder;

class CountryPricingConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $configs = [
            // United States (Default)
            [
                'country_code' => 'US',
                'country_name' => 'United States',
                'currency_code' => 'USD',
                'currency_symbol' => '$',
                'basic_monthly' => 4.99,
                'basic_quarterly' => 13.47, // 10% off
                'basic_yearly' => 47.90, // 20% off
                'premium_monthly' => 9.99,
                'premium_quarterly' => 26.97,
                'premium_yearly' => 95.90,
                'platinum_monthly' => 19.99,
                'platinum_quarterly' => 53.97,
                'platinum_yearly' => 191.90,
                'payment_methods' => ['stripe', 'paypal'],
                'display_order' => 1,
                'is_default' => true,
            ],

            // Sri Lanka
            [
                'country_code' => 'LK',
                'country_name' => 'Sri Lanka',
                'currency_code' => 'LKR',
                'currency_symbol' => 'Rs.',
                'basic_monthly' => 1500.00,
                'basic_quarterly' => 4050.00,
                'basic_yearly' => 14400.00,
                'premium_monthly' => 3000.00,
                'premium_quarterly' => 8100.00,
                'premium_yearly' => 28800.00,
                'platinum_monthly' => 6000.00,
                'platinum_quarterly' => 16200.00,
                'platinum_yearly' => 57600.00,
                'payment_methods' => ['payhere', 'webxpay', 'stripe'],
                'display_order' => 2,
            ],

            // India
            [
                'country_code' => 'IN',
                'country_name' => 'India',
                'currency_code' => 'INR',
                'currency_symbol' => '₹',
                'basic_monthly' => 399.00,
                'basic_quarterly' => 1077.00,
                'basic_yearly' => 3832.00,
                'premium_monthly' => 799.00,
                'premium_quarterly' => 2157.00,
                'premium_yearly' => 7670.00,
                'platinum_monthly' => 1599.00,
                'platinum_quarterly' => 4317.00,
                'platinum_yearly' => 15350.00,
                'payment_methods' => ['stripe', 'paypal'],
                'display_order' => 3,
            ],

            // United Kingdom
            [
                'country_code' => 'GB',
                'country_name' => 'United Kingdom',
                'currency_code' => 'GBP',
                'currency_symbol' => '£',
                'basic_monthly' => 3.99,
                'basic_quarterly' => 10.77,
                'basic_yearly' => 38.30,
                'premium_monthly' => 7.99,
                'premium_quarterly' => 21.57,
                'premium_yearly' => 76.70,
                'platinum_monthly' => 15.99,
                'platinum_quarterly' => 43.17,
                'platinum_yearly' => 153.50,
                'payment_methods' => ['stripe', 'paypal'],
                'tax_rate' => 20.00,
                'tax_name' => 'VAT',
                'display_order' => 4,
            ],

            // Germany (Euro zone)
            [
                'country_code' => 'DE',
                'country_name' => 'Germany',
                'currency_code' => 'EUR',
                'currency_symbol' => '€',
                'basic_monthly' => 4.49,
                'basic_quarterly' => 12.12,
                'basic_yearly' => 43.10,
                'premium_monthly' => 8.99,
                'premium_quarterly' => 24.27,
                'premium_yearly' => 86.30,
                'platinum_monthly' => 17.99,
                'platinum_quarterly' => 48.57,
                'platinum_yearly' => 172.70,
                'payment_methods' => ['stripe', 'paypal'],
                'tax_rate' => 19.00,
                'tax_name' => 'VAT',
                'display_order' => 5,
            ],

            // France (Euro zone)
            [
                'country_code' => 'FR',
                'country_name' => 'France',
                'currency_code' => 'EUR',
                'currency_symbol' => '€',
                'basic_monthly' => 4.49,
                'basic_quarterly' => 12.12,
                'basic_yearly' => 43.10,
                'premium_monthly' => 8.99,
                'premium_quarterly' => 24.27,
                'premium_yearly' => 86.30,
                'platinum_monthly' => 17.99,
                'platinum_quarterly' => 48.57,
                'platinum_yearly' => 172.70,
                'payment_methods' => ['stripe', 'paypal'],
                'tax_rate' => 20.00,
                'tax_name' => 'VAT',
                'display_order' => 6,
            ],

            // Australia
            [
                'country_code' => 'AU',
                'country_name' => 'Australia',
                'currency_code' => 'AUD',
                'currency_symbol' => 'A$',
                'basic_monthly' => 7.99,
                'basic_quarterly' => 21.57,
                'basic_yearly' => 76.70,
                'premium_monthly' => 14.99,
                'premium_quarterly' => 40.47,
                'premium_yearly' => 143.90,
                'platinum_monthly' => 29.99,
                'platinum_quarterly' => 80.97,
                'platinum_yearly' => 287.90,
                'payment_methods' => ['stripe', 'paypal'],
                'tax_rate' => 10.00,
                'tax_name' => 'GST',
                'display_order' => 7,
            ],

            // Canada
            [
                'country_code' => 'CA',
                'country_name' => 'Canada',
                'currency_code' => 'CAD',
                'currency_symbol' => 'C$',
                'basic_monthly' => 6.99,
                'basic_quarterly' => 18.87,
                'basic_yearly' => 67.10,
                'premium_monthly' => 12.99,
                'premium_quarterly' => 35.07,
                'premium_yearly' => 124.70,
                'platinum_monthly' => 25.99,
                'platinum_quarterly' => 70.17,
                'platinum_yearly' => 249.50,
                'payment_methods' => ['stripe', 'paypal'],
                'display_order' => 8,
            ],

            // Singapore
            [
                'country_code' => 'SG',
                'country_name' => 'Singapore',
                'currency_code' => 'SGD',
                'currency_symbol' => 'S$',
                'basic_monthly' => 6.99,
                'basic_quarterly' => 18.87,
                'basic_yearly' => 67.10,
                'premium_monthly' => 13.99,
                'premium_quarterly' => 37.77,
                'premium_yearly' => 134.30,
                'platinum_monthly' => 27.99,
                'platinum_quarterly' => 75.57,
                'platinum_yearly' => 268.70,
                'payment_methods' => ['stripe', 'paypal'],
                'tax_rate' => 9.00,
                'tax_name' => 'GST',
                'display_order' => 9,
            ],

            // UAE
            [
                'country_code' => 'AE',
                'country_name' => 'United Arab Emirates',
                'currency_code' => 'AED',
                'currency_symbol' => 'د.إ',
                'basic_monthly' => 18.00,
                'basic_quarterly' => 48.60,
                'basic_yearly' => 172.80,
                'premium_monthly' => 37.00,
                'premium_quarterly' => 99.90,
                'premium_yearly' => 355.20,
                'platinum_monthly' => 73.00,
                'platinum_quarterly' => 197.10,
                'platinum_yearly' => 700.80,
                'payment_methods' => ['stripe', 'paypal'],
                'display_order' => 10,
            ],

            // Saudi Arabia
            [
                'country_code' => 'SA',
                'country_name' => 'Saudi Arabia',
                'currency_code' => 'SAR',
                'currency_symbol' => '﷼',
                'basic_monthly' => 19.00,
                'basic_quarterly' => 51.30,
                'basic_yearly' => 182.40,
                'premium_monthly' => 37.00,
                'premium_quarterly' => 99.90,
                'premium_yearly' => 355.20,
                'platinum_monthly' => 75.00,
                'platinum_quarterly' => 202.50,
                'platinum_yearly' => 720.00,
                'payment_methods' => ['stripe', 'paypal'],
                'tax_rate' => 15.00,
                'tax_name' => 'VAT',
                'display_order' => 11,
            ],

            // Italy (Euro zone)
            [
                'country_code' => 'IT',
                'country_name' => 'Italy',
                'currency_code' => 'EUR',
                'currency_symbol' => '€',
                'basic_monthly' => 4.49,
                'basic_quarterly' => 12.12,
                'basic_yearly' => 43.10,
                'premium_monthly' => 8.99,
                'premium_quarterly' => 24.27,
                'premium_yearly' => 86.30,
                'platinum_monthly' => 17.99,
                'platinum_quarterly' => 48.57,
                'platinum_yearly' => 172.70,
                'payment_methods' => ['stripe', 'paypal'],
                'tax_rate' => 22.00,
                'tax_name' => 'IVA',
                'display_order' => 12,
            ],

            // Spain (Euro zone)
            [
                'country_code' => 'ES',
                'country_name' => 'Spain',
                'currency_code' => 'EUR',
                'currency_symbol' => '€',
                'basic_monthly' => 4.49,
                'basic_quarterly' => 12.12,
                'basic_yearly' => 43.10,
                'premium_monthly' => 8.99,
                'premium_quarterly' => 24.27,
                'premium_yearly' => 86.30,
                'platinum_monthly' => 17.99,
                'platinum_quarterly' => 48.57,
                'platinum_yearly' => 172.70,
                'payment_methods' => ['stripe', 'paypal'],
                'tax_rate' => 21.00,
                'tax_name' => 'IVA',
                'display_order' => 13,
            ],

            // Netherlands (Euro zone)
            [
                'country_code' => 'NL',
                'country_name' => 'Netherlands',
                'currency_code' => 'EUR',
                'currency_symbol' => '€',
                'basic_monthly' => 4.49,
                'basic_quarterly' => 12.12,
                'basic_yearly' => 43.10,
                'premium_monthly' => 8.99,
                'premium_quarterly' => 24.27,
                'premium_yearly' => 86.30,
                'platinum_monthly' => 17.99,
                'platinum_quarterly' => 48.57,
                'platinum_yearly' => 172.70,
                'payment_methods' => ['stripe', 'paypal'],
                'tax_rate' => 21.00,
                'tax_name' => 'BTW',
                'display_order' => 14,
            ],

            // Pakistan
            [
                'country_code' => 'PK',
                'country_name' => 'Pakistan',
                'currency_code' => 'PKR',
                'currency_symbol' => 'Rs.',
                'basic_monthly' => 1400.00,
                'basic_quarterly' => 3780.00,
                'basic_yearly' => 13440.00,
                'premium_monthly' => 2800.00,
                'premium_quarterly' => 7560.00,
                'premium_yearly' => 26880.00,
                'platinum_monthly' => 5600.00,
                'platinum_quarterly' => 15120.00,
                'platinum_yearly' => 53760.00,
                'payment_methods' => ['stripe', 'paypal'],
                'display_order' => 15,
            ],

            // Bangladesh
            [
                'country_code' => 'BD',
                'country_name' => 'Bangladesh',
                'currency_code' => 'BDT',
                'currency_symbol' => '৳',
                'basic_monthly' => 550.00,
                'basic_quarterly' => 1485.00,
                'basic_yearly' => 5280.00,
                'premium_monthly' => 1100.00,
                'premium_quarterly' => 2970.00,
                'premium_yearly' => 10560.00,
                'platinum_monthly' => 2200.00,
                'platinum_quarterly' => 5940.00,
                'platinum_yearly' => 21120.00,
                'payment_methods' => ['stripe', 'paypal'],
                'display_order' => 16,
            ],
        ];

        foreach ($configs as $config) {
            CountryPricingConfig::updateOrCreate(
                ['country_code' => $config['country_code']],
                $config
            );
        }

        $this->command->info('Country pricing configurations seeded successfully!');
    }
}
