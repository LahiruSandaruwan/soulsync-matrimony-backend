<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[a-zA-Z\s]+$/'],
            'last_name' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[a-zA-Z\s]+$/'],
            'email' => ['required', 'email:rfc,dns', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised()
            ],
            'date_of_birth' => ['required', 'date', 'before:' . now()->subYears(18)->format('Y-m-d'), 'after:' . now()->subYears(80)->format('Y-m-d')],
            'gender' => ['required', 'in:male,female,other'],
            'phone' => ['sometimes', 'string', 'min:10', 'max:15', 'regex:/^[\+]?[1-9][\d]{0,15}$/'],
            'country_code' => ['required', 'string', 'size:2', 'exists:countries,code'],
            'language' => ['sometimes', 'string', 'in:en,si,ta'],
            'terms_accepted' => ['required', 'boolean', 'accepted'],
            'privacy_accepted' => ['required', 'boolean', 'accepted'],
            'referral_code' => ['sometimes', 'string', 'size:8', 'exists:users,referral_code'],
            'marketing_consent' => ['sometimes', 'boolean'],
            'registration_source' => ['sometimes', 'string', 'in:web,mobile,api'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'first_name.regex' => 'First name can only contain letters and spaces.',
            'last_name.required' => 'Last name is required.',
            'last_name.regex' => 'Last name can only contain letters and spaces.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email address is already registered.',
            'password.required' => 'Password is required.',
            'password.confirmed' => 'Password confirmation does not match.',
            'date_of_birth.required' => 'Date of birth is required.',
            'date_of_birth.before' => 'You must be at least 18 years old to register.',
            'date_of_birth.after' => 'Please provide a valid date of birth.',
            'gender.required' => 'Gender selection is required.',
            'gender.in' => 'Please select a valid gender option.',
            'phone.regex' => 'Please provide a valid phone number.',
            'country_code.required' => 'Country selection is required.',
            'country_code.exists' => 'Please select a valid country.',
            'terms_accepted.required' => 'You must accept the terms and conditions.',
            'terms_accepted.accepted' => 'You must accept the terms and conditions.',
            'privacy_accepted.required' => 'You must accept the privacy policy.',
            'privacy_accepted.accepted' => 'You must accept the privacy policy.',
            'referral_code.exists' => 'Invalid referral code provided.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'first name',
            'last_name' => 'last name',
            'email' => 'email address',
            'date_of_birth' => 'date of birth',
            'country_code' => 'country',
            'terms_accepted' => 'terms and conditions',
            'privacy_accepted' => 'privacy policy',
            'referral_code' => 'referral code',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => trim($this->first_name),
            'last_name' => trim($this->last_name),
            'email' => strtolower(trim($this->email)),
            'country_code' => strtoupper($this->country_code ?? 'LK'),
            'language' => $this->language ?? 'en',
            'registration_source' => $this->registration_source ?? 'web',
        ]);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Additional custom validation logic
            if ($this->filled('phone') && $this->filled('country_code')) {
                // Validate phone format based on country
                $this->validatePhoneForCountry($validator);
            }

            // Check for suspicious patterns
            if ($this->isSuspiciousRegistration()) {
                $validator->errors()->add('email', 'Registration temporarily unavailable. Please try again later.');
            }
        });
    }

    /**
     * Validate phone number format based on country
     */
    private function validatePhoneForCountry($validator): void
    {
        $phone = $this->phone;
        $countryCode = $this->country_code;

        $patterns = [
            'LK' => '/^(?:\+94|0)?[1-9]\d{8}$/', // Sri Lankan phone format
            'US' => '/^(?:\+1)?[2-9]\d{2}[2-9]\d{2}\d{4}$/', // US phone format
            'IN' => '/^(?:\+91|0)?[6-9]\d{9}$/', // Indian phone format
            'GB' => '/^(?:\+44|0)?[1-9]\d{8,9}$/', // UK phone format
        ];

        if (isset($patterns[$countryCode]) && !preg_match($patterns[$countryCode], $phone)) {
            $validator->errors()->add('phone', 'Please provide a valid phone number for ' . $countryCode . '.');
        }
    }

    /**
     * Check for suspicious registration patterns
     */
    private function isSuspiciousRegistration(): bool
    {
        $ip = request()->ip();
        $email = $this->email;

        // Check for too many registrations from same IP
        $recentRegistrations = \App\Models\User::where('registration_ip', $ip)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentRegistrations >= 3) {
            return true;
        }

        // Check for disposable email domains
        $disposableDomains = ['tempmail.org', '10minutemail.com', 'guerrillamail.com'];
        $emailDomain = substr(strrchr($email, '@'), 1);
        
        if (in_array($emailDomain, $disposableDomains)) {
            return true;
        }

        return false;
    }
} 