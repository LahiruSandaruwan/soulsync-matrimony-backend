<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
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
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'remember_me' => ['sometimes', 'boolean'],
            'device_name' => ['sometimes', 'string', 'max:255'],
            'device_type' => ['sometimes', 'string', 'in:web,mobile,tablet,desktop'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'email' => 'email address',
            'device_name' => 'device name',
            'device_type' => 'device type',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim($this->email)),
            'device_name' => $this->device_name ?? $this->getUserAgent(),
            'device_type' => $this->device_type ?? $this->detectDeviceType(),
        ]);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check rate limiting
            if ($this->hasTooManyLoginAttempts()) {
                $this->fireLockoutEvent();
                
                $seconds = RateLimiter::availableIn($this->throttleKey());
                
                throw ValidationException::withMessages([
                    'email' => trans('auth.throttle', [
                        'seconds' => $seconds,
                        'minutes' => ceil($seconds / 60),
                    ]),
                ]);
            }
        });
    }

    /**
     * Attempt to authenticate the request's credentials.
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (!Auth::attempt($this->only('email', 'password'), $this->boolean('remember_me'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     */
    public function ensureIsNotRateLimited(): void
    {
        if (!$this->hasTooManyLoginAttempts()) {
            return;
        }

        event(new \Illuminate\Auth\Events\Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Determine if the user has too many failed login attempts.
     */
    public function hasTooManyLoginAttempts(): bool
    {
        return RateLimiter::tooManyAttempts($this->throttleKey(), 5);
    }

    /**
     * Increment the login attempts for the user.
     */
    public function incrementLoginAttempts(): void
    {
        RateLimiter::hit($this->throttleKey(), 900); // 15 minutes lockout
    }

    /**
     * Clear the login locks for the given user credentials.
     */
    public function clearLoginAttempts(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Fire an event when a lockout occurs.
     */
    protected function fireLockoutEvent(): void
    {
        event(new \Illuminate\Auth\Events\Lockout($this));
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return strtolower($this->input('email')) . '|' . $this->ip();
    }

    /**
     * Get user agent for device name
     */
    private function getUserAgent(): string
    {
        $userAgent = $this->header('User-Agent', 'Unknown Device');
        
        // Extract browser name from user agent
        if (strpos($userAgent, 'Chrome') !== false) {
            return 'Chrome Browser';
        } elseif (strpos($userAgent, 'Firefox') !== false) {
            return 'Firefox Browser';
        } elseif (strpos($userAgent, 'Safari') !== false) {
            return 'Safari Browser';
        } elseif (strpos($userAgent, 'Edge') !== false) {
            return 'Edge Browser';
        }
        
        return 'Unknown Device';
    }

    /**
     * Detect device type from user agent
     */
    private function detectDeviceType(): string
    {
        $userAgent = $this->header('User-Agent', '');
        
        if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
            if (preg_match('/iPad/', $userAgent)) {
                return 'tablet';
            }
            return 'mobile';
        }
        
        return 'desktop';
    }

    /**
     * Get the login username to be used by the controller.
     */
    public function username(): string
    {
        return 'email';
    }
} 