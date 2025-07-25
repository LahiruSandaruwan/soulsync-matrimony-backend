<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Auth\Passwords\CanResetPassword;

class User extends Authenticatable implements MustVerifyEmail, \Illuminate\Contracts\Auth\CanResetPassword
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, CanResetPassword;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name', // Keep for backward compatibility
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'date_of_birth',
        'gender',
        'country_code',
        'language',
        'status',
        'profile_status',
        'registration_ip',
        'registration_method',
        'social_id',
        'social_data',
        'referral_code',
        'referred_by',
        'is_premium',
        'premium_expires_at',
        'last_active_at',
        // Additional fields from v2 migration
        'profile_completion_percentage',
        'completed_sections',
        'current_city',
        'current_state',
        'latitude',
        'longitude',
        'email_notifications',
        'sms_notifications',
        'push_notifications',
        'profile_visibility',
        'hide_last_seen',
        'incognito_mode',
        'email_verified',
        'phone_verified',
        'photo_verified',
        'id_verified',
        'verification_documents',
        'two_factor_secret',
        'two_factor_enabled',
        'recovery_codes',
        'password_changed_at',
        'failed_login_attempts',
        'locked_until',
        'device_tokens',
        'last_login_ip',
        'last_device',
        'last_password_reset',
        'login_count',
        'profile_views_received',
        'profile_views_given',
        'likes_received',
        'likes_given',
        'first_login_at',
        'premium_features_used',
        'super_likes_count',
        'boosts_used',
        'last_boost_at',
        'preferred_min_age',
        'preferred_max_age',
        'preferred_distance_km',
        'trial_used',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'social_id',
        'social_data',
        'registration_ip',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'social_data' => 'array',
            'is_premium' => 'boolean',
            'premium_expires_at' => 'datetime',
            'last_active_at' => 'datetime',
            // Additional casts for v2 fields
            'completed_sections' => 'array',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'email_notifications' => 'boolean',
            'sms_notifications' => 'boolean',
            'push_notifications' => 'boolean',
            'hide_last_seen' => 'boolean',
            'incognito_mode' => 'boolean',
            'email_verified' => 'boolean',
            'phone_verified' => 'boolean',
            'photo_verified' => 'boolean',
            'id_verified' => 'boolean',
            'verification_documents' => 'array',
            'two_factor_enabled' => 'boolean',
            'recovery_codes' => 'array',
            'password_changed_at' => 'datetime',
            'locked_until' => 'datetime',
            'device_tokens' => 'array',
            'last_password_reset' => 'datetime',
            'first_login_at' => 'datetime',
            'premium_features_used' => 'array',
            'last_boost_at' => 'datetime',
        ];
    }

    /**
     * Send the password reset notification.
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new \Illuminate\Auth\Notifications\ResetPassword($token));
    }

    // Relationships

    /**
     * Get the user's profile.
     */
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    /**
     * Get the user's preferences.
     */
    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    /**
     * Get the user's photos.
     */
    public function photos(): HasMany
    {
        return $this->hasMany(UserPhoto::class);
    }

    /**
     * Get the user's profile picture.
     */
    public function profilePicture(): HasOne
    {
        return $this->hasOne(UserPhoto::class)->where('is_profile_picture', true);
    }

    /**
     * Get the user's horoscope.
     */
    public function horoscope(): HasOne
    {
        return $this->hasOne(Horoscope::class);
    }

    /**
     * Get the user's interests.
     */
    public function interests(): BelongsToMany
    {
        return $this->belongsToMany(Interest::class, 'user_interests');
    }

    /**
     * Get the user's sent matches.
     */
    public function sentMatches(): HasMany
    {
        return $this->hasMany(UserMatch::class, 'user_id');
    }

    /**
     * Get the user's received matches.
     */
    public function receivedMatches(): HasMany
    {
        return $this->hasMany(UserMatch::class, 'matched_user_id');
    }

    /**
     * Get all matches for the user (as sender or receiver).
     */
    public function matches()
    {
        return $this->hasMany(UserMatch::class, 'user_id')
            ->orWhere('matched_user_id', $this->id);
    }

    /**
     * Get the user's conversations.
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'user_one_id')
            ->orWhere('user_two_id', $this->id);
    }

    /**
     * Get the user's sent messages.
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Get the user's received messages.
     */
    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    /**
     * Get the user's subscriptions.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the user's active subscription.
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->where('status', 'active');
    }

    /**
     * Get the user who referred this user.
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    /**
     * Get users referred by this user.
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    /**
     * Get the user's notifications.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get reports made by this user.
     */
    public function reportsMade(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    /**
     * Get reports made against this user.
     */
    public function reportsReceived(): HasMany
    {
        return $this->hasMany(Report::class, 'reported_user_id');
    }

    // Accessors and Mutators

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Get the user's age.
     */
    public function getAgeAttribute(): int
    {
        return $this->date_of_birth->age;
    }

    /**
     * Check if user is premium.
     */
    public function getIsPremiumActiveAttribute(): bool
    {
        return $this->is_premium && 
               $this->premium_expires_at && 
               $this->premium_expires_at->isFuture();
    }

    // Scopes

    /**
     * Scope active users.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope approved profiles.
     */
    public function scopeApproved($query)
    {
        return $query->where('profile_status', 'approved');
    }

    /**
     * Scope premium users.
     */
    public function scopePremium($query)
    {
        return $query->where('is_premium', true)
                    ->where('premium_expires_at', '>', now());
    }

    /**
     * Scope by gender.
     */
    public function scopeByGender($query, $gender)
    {
        return $query->where('gender', $gender);
    }

    /**
     * Scope by country.
     */
    public function scopeByCountry($query, $countryCode)
    {
        return $query->where('country_code', $countryCode);
    }

    /**
     * Use 'id' for route model binding and allow all users to be resolved.
     */
    public function getRouteKeyName()
    {
        return 'id';
    }

    public function resolveRouteBinding(
        $value,
        $field = null
    ) {
        // Always resolve by id, do not filter by status/profile_status
        return $this->where($field ?? $this->getRouteKeyName(), $value)->first();
    }
}
