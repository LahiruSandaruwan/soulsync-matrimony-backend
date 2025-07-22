<?php

namespace App\Http\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscribeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'plan_type' => ['required', 'string', 'in:basic,premium,platinum'],
            'payment_method' => ['required', 'string', 'in:stripe,paypal,payhere,webxpay'],
            'duration_months' => ['sometimes', 'integer', 'in:1,3,6,12'],
            'auto_renewal' => ['sometimes', 'boolean'],
            'payment_token' => ['required', 'string', 'min:10', 'max:500'],
            'billing_details' => ['sometimes', 'array'],
            'billing_details.name' => ['sometimes', 'string', 'max:255'],
            'billing_details.email' => ['sometimes', 'email', 'max:255'],
            'billing_details.phone' => ['sometimes', 'string', 'max:20'],
            'billing_details.address' => ['sometimes', 'array'],
            'billing_details.address.line1' => ['sometimes', 'string', 'max:255'],
            'billing_details.address.line2' => ['sometimes', 'string', 'max:255'],
            'billing_details.address.city' => ['sometimes', 'string', 'max:100'],
            'billing_details.address.state' => ['sometimes', 'string', 'max:100'],
            'billing_details.address.postal_code' => ['sometimes', 'string', 'max:20'],
            'billing_details.address.country' => ['sometimes', 'string', 'size:2'],
            'coupon_code' => ['sometimes', 'string', 'max:50'],
            'terms_accepted' => ['required', 'boolean', 'accepted'],
            'currency_preference' => ['sometimes', 'string', 'in:USD,LKR'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'plan_type.required' => 'Please select a subscription plan.',
            'plan_type.in' => 'Please select a valid subscription plan.',
            'payment_method.required' => 'Please select a payment method.',
            'payment_method.in' => 'Please select a valid payment method.',
            'duration_months.in' => 'Please select a valid subscription duration.',
            'payment_token.required' => 'Payment information is required.',
            'payment_token.min' => 'Invalid payment information provided.',
            'billing_details.name.required' => 'Billing name is required.',
            'billing_details.email.email' => 'Please provide a valid billing email.',
            'billing_details.address.country.size' => 'Country code must be exactly 2 characters.',
            'terms_accepted.required' => 'You must accept the subscription terms.',
            'terms_accepted.accepted' => 'You must accept the subscription terms.',
            'currency_preference.in' => 'Please select a valid currency.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'plan_type' => 'subscription plan',
            'payment_method' => 'payment method',
            'duration_months' => 'subscription duration',
            'payment_token' => 'payment information',
            'billing_details.name' => 'billing name',
            'billing_details.email' => 'billing email',
            'billing_details.phone' => 'billing phone',
            'billing_details.address.line1' => 'billing address',
            'billing_details.address.city' => 'billing city',
            'billing_details.address.state' => 'billing state',
            'billing_details.address.postal_code' => 'postal code',
            'billing_details.address.country' => 'billing country',
            'coupon_code' => 'coupon code',
            'terms_accepted' => 'subscription terms',
            'currency_preference' => 'currency preference',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $user = auth()->user();
        
        $this->merge([
            'duration_months' => $this->duration_months ?? 1,
            'auto_renewal' => $this->auto_renewal ?? true,
            'currency_preference' => $this->currency_preference ?? ($user->country_code === 'LK' ? 'LKR' : 'USD'),
        ]);

        // Clean billing details
        if ($this->filled('billing_details')) {
            $billingDetails = $this->input('billing_details');
            
            if (isset($billingDetails['name'])) {
                $billingDetails['name'] = trim($billingDetails['name']);
            }
            
            if (isset($billingDetails['email'])) {
                $billingDetails['email'] = strtolower(trim($billingDetails['email']));
            }
            
            if (isset($billingDetails['phone'])) {
                $billingDetails['phone'] = preg_replace('/[^+\d]/', '', $billingDetails['phone']);
            }

            if (isset($billingDetails['address']['country'])) {
                $billingDetails['address']['country'] = strtoupper($billingDetails['address']['country']);
            }

            $this->merge(['billing_details' => $billingDetails]);
        }

        // Clean coupon code
        if ($this->filled('coupon_code')) {
            $this->merge(['coupon_code' => strtoupper(trim($this->coupon_code))]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = auth()->user();

            // Validate payment method availability for user's country
            $this->validatePaymentMethodAvailability($validator, $user);

            // Check for existing active subscription
            $this->validateNoActiveSubscription($validator, $user);

            // Validate coupon code if provided
            if ($this->filled('coupon_code')) {
                $this->validateCouponCode($validator);
            }

            // Validate billing details completeness for certain payment methods
            $this->validateBillingDetailsCompleteness($validator);

            // Check subscription limits
            $this->validateSubscriptionLimits($validator, $user);
        });
    }

    /**
     * Validate payment method availability for user's country
     */
    private function validatePaymentMethodAvailability($validator, $user): void
    {
        $paymentMethod = $this->input('payment_method');
        $userCountry = $user->country_code;

        $paymentMethodAvailability = [
            'stripe' => ['*'], // Available worldwide
            'paypal' => ['US', 'CA', 'GB', 'AU', 'DE', 'FR', 'IT', 'ES', 'NL', 'BE'], // Selected countries
            'payhere' => ['LK'], // Only Sri Lanka
            'webxpay' => ['LK'], // Only Sri Lanka
        ];

        if (isset($paymentMethodAvailability[$paymentMethod])) {
            $allowedCountries = $paymentMethodAvailability[$paymentMethod];
            
            if (!in_array('*', $allowedCountries) && !in_array($userCountry, $allowedCountries)) {
                $validator->errors()->add(
                    'payment_method', 
                    "Payment method '{$paymentMethod}' is not available in your country."
                );
            }
        }
    }

    /**
     * Check if user already has an active subscription
     */
    private function validateNoActiveSubscription($validator, $user): void
    {
        $activeSubscription = $user->subscriptions()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->exists();

        if ($activeSubscription) {
            $validator->errors()->add(
                'plan_type',
                'You already have an active subscription. Please cancel or wait for it to expire before subscribing to a new plan.'
            );
        }
    }

    /**
     * Validate coupon code
     */
    private function validateCouponCode($validator): void
    {
        $couponCode = $this->input('coupon_code');
        
        // Find and validate coupon
        $coupon = \App\Models\Coupon::findByCode($couponCode);
        
        if (!$coupon) {
            $validator->errors()->add('coupon_code', 'Invalid coupon code.');
            return;
        }

        // Validate coupon for current user and plan
        $user = $this->user();
        $planType = $this->input('plan_type');
        $amount = $this->getPlanAmount($planType);
        
        $validation = $coupon->isValid($user, $planType, $amount);
        
        if (!$validation['valid']) {
            foreach ($validation['errors'] as $error) {
                $validator->errors()->add('coupon_code', $error);
            }
        }
        // $coupon = Coupon::where('code', $couponCode)
        //     ->where('is_active', true)
        //     ->where('expires_at', '>', now())
        //     ->first();

        // if (!$coupon) {
        //     $validator->errors()->add('coupon_code', 'Invalid or expired coupon code.');
        // }
    }

    /**
     * Validate billing details completeness for certain payment methods
     */
    private function validateBillingDetailsCompleteness($validator): void
    {
        $paymentMethod = $this->input('payment_method');
        $billingDetails = $this->input('billing_details', []);

        // PayPal and some other methods require complete billing information
        $requireCompleteBilling = ['paypal'];

        if (in_array($paymentMethod, $requireCompleteBilling)) {
            $requiredFields = ['name', 'email', 'address.line1', 'address.city', 'address.country'];

            foreach ($requiredFields as $field) {
                $value = data_get($billingDetails, $field);
                if (empty($value)) {
                    $fieldName = str_replace('.', ' ', $field);
                    $validator->errors()->add(
                        "billing_details.{$field}",
                        "Billing {$fieldName} is required for {$paymentMethod} payments."
                    );
                }
            }
        }
    }

    /**
     * Validate subscription limits and restrictions
     */
    private function validateSubscriptionLimits($validator, $user): void
    {
        // Check if user has reached maximum subscription attempts
        $recentFailedAttempts = $user->subscriptions()
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($recentFailedAttempts >= 5) {
            $validator->errors()->add(
                'payment_method',
                'Too many failed subscription attempts. Please try again after 24 hours or contact support.'
            );
        }

        // Check user's account status
        if ($user->status !== 'active') {
            $validator->errors()->add(
                'plan_type',
                'Your account must be active to purchase a subscription.'
            );
        }

        // Check if user's profile is complete enough for premium features
        if ($this->input('plan_type') !== 'basic' && $user->profile_completion_percentage < 50) {
            $validator->errors()->add(
                'plan_type',
                'Please complete at least 50% of your profile before upgrading to premium plans.'
            );
        }
    }

    /**
     * Get the plan pricing for the user
     */
    public function getPlanPricing(): array
    {
        $planType = $this->input('plan_type');
        $duration = $this->input('duration_months', 1);
        $user = auth()->user();

        $basePrices = [
            'basic' => 4.99,
            'premium' => 9.99,
            'platinum' => 19.99,
        ];

        $basePrice = $basePrices[$planType];
        
        // Apply duration discounts
        $discounts = [1 => 0, 3 => 0.1, 6 => 0.15, 12 => 0.2];
        $discount = $discounts[$duration] ?? 0;
        
        $totalPriceUSD = $basePrice * $duration * (1 - $discount);

        // Convert to local currency for Sri Lankan users
        if ($user->country_code === 'LK') {
            $exchangeRate = \App\Models\ExchangeRate::getRate('USD', 'LKR') ?? 300;
            $totalPriceLKR = $totalPriceUSD * $exchangeRate;
            
            return [
                'amount_usd' => $totalPriceUSD,
                'amount_local' => $totalPriceLKR,
                'currency' => 'LKR',
                'exchange_rate' => $exchangeRate,
                'discount_percentage' => $discount * 100,
            ];
        }

        return [
            'amount_usd' => $totalPriceUSD,
            'amount_local' => $totalPriceUSD,
            'currency' => 'USD',
            'exchange_rate' => 1,
            'discount_percentage' => $discount * 100,
        ];
    }
} 