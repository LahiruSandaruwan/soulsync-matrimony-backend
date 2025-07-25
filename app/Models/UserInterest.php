<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserInterest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'interest_id',
        'is_primary',
        'added_at',
        'removed_at',
        'metadata'
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'added_at' => 'datetime',
        'removed_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $dates = [
        'added_at',
        'removed_at',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function interest(): BelongsTo
    {
        return $this->belongsTo(Interest::class);
    }

    // Methods
    public function isPrimary(): bool
    {
        return $this->is_primary;
    }

    public function isActive(): bool
    {
        return is_null($this->removed_at);
    }

    public function isRemoved(): bool
    {
        return !is_null($this->removed_at);
    }

    public function markAsPrimary(): bool
    {
        // Remove primary from other interests in same category
        self::where('user_id', $this->user_id)
            ->where('interest_id', '!=', $this->interest_id)
            ->whereHas('interest', function ($q) {
                $q->where('category', $this->interest->category);
            })
            ->update(['is_primary' => false]);

        return $this->update(['is_primary' => true]);
    }

    public function remove(): bool
    {
        return $this->update(['removed_at' => now()]);
    }

    public function restore(): bool
    {
        return $this->update(['removed_at' => null]);
    }

    public function getMetadata(string $key = null)
    {
        if ($key) {
            return $this->metadata[$key] ?? null;
        }

        return $this->metadata;
    }

    public function setMetadata(string $key, $value): bool
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;

        return $this->update(['metadata' => $metadata]);
    }

    // Static methods
    public static function addInterestToUser(User $user, Interest $interest, bool $isPrimary = false): self
    {
        return self::updateOrCreate(
            [
                'user_id' => $user->id,
                'interest_id' => $interest->id,
            ],
            [
                'is_primary' => $isPrimary,
                'added_at' => now(),
                'removed_at' => null,
            ]
        );
    }

    public static function removeInterestFromUser(User $user, Interest $interest): bool
    {
        return self::where('user_id', $user->id)
                  ->where('interest_id', $interest->id)
                  ->update(['removed_at' => now()]);
    }

    public static function getUserPrimaryInterests(User $user): array
    {
        return self::where('user_id', $user->id)
                  ->where('is_primary', true)
                  ->whereNull('removed_at')
                  ->with('interest')
                  ->get()
                  ->pluck('interest')
                  ->toArray();
    }

    public static function getUserInterestsByCategory(User $user, string $category): array
    {
        return self::where('user_id', $user->id)
                  ->whereNull('removed_at')
                  ->whereHas('interest', function ($q) use ($category) {
                      $q->where('category', $category);
                  })
                  ->with('interest')
                  ->get()
                  ->pluck('interest')
                  ->toArray();
    }

    public static function getCommonInterests(User $user1, User $user2): array
    {
        $user1Interests = self::where('user_id', $user1->id)
                             ->whereNull('removed_at')
                             ->pluck('interest_id')
                             ->toArray();

        $user2Interests = self::where('user_id', $user2->id)
                             ->whereNull('removed_at')
                             ->pluck('interest_id')
                             ->toArray();

        $commonInterestIds = array_intersect($user1Interests, $user2Interests);

        return Interest::whereIn('id', $commonInterestIds)->get()->toArray();
    }

    public static function getInterestCompatibilityScore(User $user1, User $user2): float
    {
        $user1Interests = self::where('user_id', $user1->id)
                             ->whereNull('removed_at')
                             ->pluck('interest_id')
                             ->toArray();

        $user2Interests = self::where('user_id', $user2->id)
                             ->whereNull('removed_at')
                             ->pluck('interest_id')
                             ->toArray();

        $commonInterests = array_intersect($user1Interests, $user2Interests);
        $totalInterests = array_unique(array_merge($user1Interests, $user2Interests));

        if (empty($totalInterests)) {
            return 0.0;
        }

        return round((count($commonInterests) / count($totalInterests)) * 100, 2);
    }

    public static function getPopularInterestsForUser(User $user, int $limit = 5): array
    {
        $userInterests = self::where('user_id', $user->id)
                            ->whereNull('removed_at')
                            ->pluck('interest_id')
                            ->toArray();

        return Interest::whereIn('id', $userInterests)
                      ->withCount('users')
                      ->orderBy('users_count', 'desc')
                      ->limit($limit)
                      ->get()
                      ->toArray();
    }

    public static function getRecommendedInterests(User $user, int $limit = 10): array
    {
        $userInterests = self::where('user_id', $user->id)
                            ->whereNull('removed_at')
                            ->pluck('interest_id')
                            ->toArray();

        return Interest::whereNotIn('id', $userInterests)
                      ->where('is_active', true)
                      ->withCount('users')
                      ->orderBy('users_count', 'desc')
                      ->limit($limit)
                      ->get()
                      ->toArray();
    }

    public static function getInterestStats(User $user): array
    {
        $totalInterests = self::where('user_id', $user->id)
                             ->whereNull('removed_at')
                             ->count();

        $primaryInterests = self::where('user_id', $user->id)
                               ->where('is_primary', true)
                               ->whereNull('removed_at')
                               ->count();

        $categories = self::where('user_id', $user->id)
                         ->whereNull('removed_at')
                         ->with('interest')
                         ->get()
                         ->pluck('interest.category')
                         ->unique()
                         ->count();

        return [
            'total_interests' => $totalInterests,
            'primary_interests' => $primaryInterests,
            'categories' => $categories,
            'completion_percentage' => min(100, ($totalInterests / 10) * 100), // Assuming 10 interests is complete
        ];
    }
} 