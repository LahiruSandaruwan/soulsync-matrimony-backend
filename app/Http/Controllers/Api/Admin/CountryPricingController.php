<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CountryPricingConfig;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class CountryPricingController extends Controller
{
    public function __construct(
        private PricingService $pricingService
    ) {}

    /**
     * List all country pricing configurations
     */
    public function index(Request $request): JsonResponse
    {
        $query = CountryPricingConfig::query();

        // Filter by active status
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        // Search by country name or code
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('country_name', 'like', "%{$search}%")
                  ->orWhere('country_code', 'like', "%{$search}%")
                  ->orWhere('currency_code', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'display_order');
        $sortDir = $request->get('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);

        $configs = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $configs,
        ]);
    }

    /**
     * Get a single country pricing configuration
     */
    public function show(string $countryCode): JsonResponse
    {
        $config = CountryPricingConfig::where('country_code', strtoupper($countryCode))->first();

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Country pricing configuration not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $config,
        ]);
    }

    /**
     * Create a new country pricing configuration
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->validationRules());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if country already exists
        if (CountryPricingConfig::where('country_code', strtoupper($request->country_code))->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Country pricing configuration already exists',
            ], 409);
        }

        $data = $request->all();
        $data['country_code'] = strtoupper($data['country_code']);
        $data['currency_code'] = strtoupper($data['currency_code']);

        $config = CountryPricingConfig::create($data);

        // Clear cache
        $this->pricingService->clearCache($config->country_code);

        return response()->json([
            'success' => true,
            'message' => 'Country pricing configuration created successfully',
            'data' => $config,
        ], 201);
    }

    /**
     * Update a country pricing configuration
     */
    public function update(Request $request, string $countryCode): JsonResponse
    {
        $config = CountryPricingConfig::where('country_code', strtoupper($countryCode))->first();

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Country pricing configuration not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), $this->validationRules(true));

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->all();
        if (isset($data['currency_code'])) {
            $data['currency_code'] = strtoupper($data['currency_code']);
        }

        $config->update($data);

        // Clear cache
        $this->pricingService->clearCache($config->country_code);

        return response()->json([
            'success' => true,
            'message' => 'Country pricing configuration updated successfully',
            'data' => $config->fresh(),
        ]);
    }

    /**
     * Delete (deactivate) a country pricing configuration
     */
    public function destroy(string $countryCode): JsonResponse
    {
        $config = CountryPricingConfig::where('country_code', strtoupper($countryCode))->first();

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Country pricing configuration not found',
            ], 404);
        }

        // Don't allow deleting default config
        if ($config->is_default) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete the default country pricing configuration',
            ], 403);
        }

        // Soft delete by deactivating
        $config->update(['is_active' => false]);

        // Clear cache
        $this->pricingService->clearCache($config->country_code);

        return response()->json([
            'success' => true,
            'message' => 'Country pricing configuration deactivated successfully',
        ]);
    }

    /**
     * Bulk update pricing for multiple countries
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'updates' => 'required|array|min:1',
            'updates.*.country_code' => 'required|string|size:2',
            'updates.*.basic_monthly' => 'nullable|numeric|min:0',
            'updates.*.basic_quarterly' => 'nullable|numeric|min:0',
            'updates.*.basic_yearly' => 'nullable|numeric|min:0',
            'updates.*.premium_monthly' => 'nullable|numeric|min:0',
            'updates.*.premium_quarterly' => 'nullable|numeric|min:0',
            'updates.*.premium_yearly' => 'nullable|numeric|min:0',
            'updates.*.platinum_monthly' => 'nullable|numeric|min:0',
            'updates.*.platinum_quarterly' => 'nullable|numeric|min:0',
            'updates.*.platinum_yearly' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updated = [];
        $notFound = [];

        foreach ($request->updates as $update) {
            $countryCode = strtoupper($update['country_code']);
            $config = CountryPricingConfig::where('country_code', $countryCode)->first();

            if (!$config) {
                $notFound[] = $countryCode;
                continue;
            }

            unset($update['country_code']);
            $config->update($update);
            $updated[] = $countryCode;

            // Clear cache
            $this->pricingService->clearCache($countryCode);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bulk update completed',
            'data' => [
                'updated' => $updated,
                'not_found' => $notFound,
            ],
        ]);
    }

    /**
     * Apply percentage increase/decrease to all prices
     */
    public function adjustPrices(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'percentage' => 'required|numeric|min:-50|max:100',
            'countries' => 'nullable|array',
            'countries.*' => 'string|size:2',
            'plans' => 'nullable|array',
            'plans.*' => 'string|in:basic,premium,platinum',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $percentage = $request->percentage;
        $multiplier = 1 + ($percentage / 100);

        $query = CountryPricingConfig::query();

        // Filter by specific countries if provided
        if ($request->has('countries') && !empty($request->countries)) {
            $query->whereIn('country_code', array_map('strtoupper', $request->countries));
        }

        $configs = $query->get();
        $plans = $request->plans ?? ['basic', 'premium', 'platinum'];

        foreach ($configs as $config) {
            $updates = [];
            foreach ($plans as $plan) {
                $updates["{$plan}_monthly"] = round($config->{"{$plan}_monthly"} * $multiplier, 2);
                $updates["{$plan}_quarterly"] = round($config->{"{$plan}_quarterly"} * $multiplier, 2);
                $updates["{$plan}_yearly"] = round($config->{"{$plan}_yearly"} * $multiplier, 2);
            }
            $config->update($updates);

            // Clear cache
            $this->pricingService->clearCache($config->country_code);
        }

        return response()->json([
            'success' => true,
            'message' => "Prices adjusted by {$percentage}% for " . $configs->count() . " countries",
            'data' => [
                'countries_updated' => $configs->pluck('country_code'),
                'percentage' => $percentage,
                'plans_affected' => $plans,
            ],
        ]);
    }

    /**
     * Validation rules for country pricing
     */
    private function validationRules(bool $isUpdate = false): array
    {
        $countryCodeRule = $isUpdate ? 'prohibited' : 'required|string|size:2';

        return [
            'country_code' => $countryCodeRule,
            'country_name' => ($isUpdate ? 'nullable' : 'required') . '|string|max:100',
            'currency_code' => ($isUpdate ? 'nullable' : 'required') . '|string|size:3',
            'currency_symbol' => ($isUpdate ? 'nullable' : 'required') . '|string|max:10',
            'basic_monthly' => ($isUpdate ? 'nullable' : 'required') . '|numeric|min:0',
            'basic_quarterly' => ($isUpdate ? 'nullable' : 'required') . '|numeric|min:0',
            'basic_yearly' => ($isUpdate ? 'nullable' : 'required') . '|numeric|min:0',
            'premium_monthly' => ($isUpdate ? 'nullable' : 'required') . '|numeric|min:0',
            'premium_quarterly' => ($isUpdate ? 'nullable' : 'required') . '|numeric|min:0',
            'premium_yearly' => ($isUpdate ? 'nullable' : 'required') . '|numeric|min:0',
            'platinum_monthly' => ($isUpdate ? 'nullable' : 'required') . '|numeric|min:0',
            'platinum_quarterly' => ($isUpdate ? 'nullable' : 'required') . '|numeric|min:0',
            'platinum_yearly' => ($isUpdate ? 'nullable' : 'required') . '|numeric|min:0',
            'quarterly_discount' => 'nullable|numeric|min:0|max:100',
            'yearly_discount' => 'nullable|numeric|min:0|max:100',
            'payment_methods' => 'nullable|array',
            'payment_methods.*' => 'string|in:stripe,paypal,payhere,webxpay',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'tax_name' => 'nullable|string|max:50',
            'tax_inclusive' => 'nullable|boolean',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
