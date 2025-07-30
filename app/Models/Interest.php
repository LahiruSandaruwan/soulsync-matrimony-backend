<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class Interest extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'category',
        'subcategory',
        'icon',
        'color',
        'is_active',
        'is_featured',
        'sort_order',
        'metadata',
        'created_by',
        'updated_by',
        'deleted_at',
        'deleted_by',
        'slug',
        'matching_weight',
        'user_count',
        'popularity_score',
        'is_trending',
        'localization'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
        'deleted_at' => 'datetime',
    ];

    protected $dates = [
        'deleted_at',
    ];

    // Interest Categories
    const CATEGORY_HOBBIES = 'hobbies';
    const CATEGORY_SPORTS = 'sports';
    const CATEGORY_MUSIC = 'music';
    const CATEGORY_MOVIES = 'movies';
    const CATEGORY_BOOKS = 'books';
    const CATEGORY_TRAVEL = 'travel';
    const CATEGORY_FOOD = 'food';
    const CATEGORY_TECHNOLOGY = 'technology';
    const CATEGORY_ART = 'art';
    const CATEGORY_FASHION = 'fashion';
    const CATEGORY_FITNESS = 'fitness';
    const CATEGORY_NATURE = 'nature';
    const CATEGORY_BUSINESS = 'business';
    const CATEGORY_EDUCATION = 'education';
    const CATEGORY_VOLUNTEERING = 'volunteering';
    const CATEGORY_GAMING = 'gaming';
    const CATEGORY_PHOTOGRAPHY = 'photography';
    const CATEGORY_DANCE = 'dance';
    const CATEGORY_COOKING = 'cooking';
    const CATEGORY_GARDENING = 'gardening';
    const CATEGORY_PETS = 'pets';
    const CATEGORY_MEDITATION = 'meditation';
    const CATEGORY_YOGA = 'yoga';
    const CATEGORY_READING = 'reading';
    const CATEGORY_WRITING = 'writing';
    const CATEGORY_PAINTING = 'painting';
    const CATEGORY_CRAFTING = 'crafting';
    const CATEGORY_COLLECTING = 'collecting';
    const CATEGORY_OTHER = 'other';

    // Interest Status
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_PENDING = 'pending';
    const STATUS_DELETED = 'deleted';

    // Relationships
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_interests')
                    ->withPivot(['is_primary', 'added_at', 'removed_at'])
                    ->withTimestamps();
    }

    public function userInterests(): HasMany
    {
        return $this->hasMany(UserInterest::class);
    }

    public function createdBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'interest_creators')
                    ->wherePivot('role', 'creator');
    }

    public function updatedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'interest_updaters')
                    ->wherePivot('role', 'updater');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeBySubcategory(Builder $query, string $subcategory): Builder
    {
        return $query->where('subcategory', $subcategory);
    }

    public function scopePopular(Builder $query, int $limit = 10): Builder
    {
        return $query->withCount('users')
                    ->orderBy('users_count', 'desc')
                    ->limit($limit);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc')
                    ->orderBy('name', 'asc');
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhere('category', 'like', "%{$search}%")
              ->orWhere('subcategory', 'like', "%{$search}%");
        });
    }

    public function scopeByUser(Builder $query, User $user): Builder
    {
        return $query->whereHas('users', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });
    }

    public function scopeNotByUser(Builder $query, User $user): Builder
    {
        return $query->whereDoesntHave('users', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });
    }

    public function scopeCompatibleWith(Builder $query, User $user): Builder
    {
        return $query->whereHas('users', function ($q) use ($user) {
            $q->where('user_id', '!=', $user->id)
              ->whereHas('preferences', function ($pq) use ($user) {
                  $pq->where('user_id', $user->id);
              });
        });
    }

    // Methods
    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isFeatured(): bool
    {
        return $this->is_featured;
    }

    public function isDeleted(): bool
    {
        return !is_null($this->deleted_at);
    }

    public function getCategoryLabel(): string
    {
        return match($this->category) {
            self::CATEGORY_HOBBIES => 'Hobbies',
            self::CATEGORY_SPORTS => 'Sports',
            self::CATEGORY_MUSIC => 'Music',
            self::CATEGORY_MOVIES => 'Movies',
            self::CATEGORY_BOOKS => 'Books',
            self::CATEGORY_TRAVEL => 'Travel',
            self::CATEGORY_FOOD => 'Food',
            self::CATEGORY_TECHNOLOGY => 'Technology',
            self::CATEGORY_ART => 'Art',
            self::CATEGORY_FASHION => 'Fashion',
            self::CATEGORY_FITNESS => 'Fitness',
            self::CATEGORY_NATURE => 'Nature',
            self::CATEGORY_BUSINESS => 'Business',
            self::CATEGORY_EDUCATION => 'Education',
            self::CATEGORY_VOLUNTEERING => 'Volunteering',
            self::CATEGORY_GAMING => 'Gaming',
            self::CATEGORY_PHOTOGRAPHY => 'Photography',
            self::CATEGORY_DANCE => 'Dance',
            self::CATEGORY_COOKING => 'Cooking',
            self::CATEGORY_GARDENING => 'Gardening',
            self::CATEGORY_PETS => 'Pets',
            self::CATEGORY_MEDITATION => 'Meditation',
            self::CATEGORY_YOGA => 'Yoga',
            self::CATEGORY_READING => 'Reading',
            self::CATEGORY_WRITING => 'Writing',
            self::CATEGORY_PAINTING => 'Painting',
            self::CATEGORY_CRAFTING => 'Crafting',
            self::CATEGORY_COLLECTING => 'Collecting',
            self::CATEGORY_OTHER => 'Other',
            default => 'Unknown'
        };
    }

    public function getUserCount(): int
    {
        return $this->users()->count();
    }

    public function isPrimaryForUser(User $user): bool
    {
        $userInterest = $this->userInterests()->where('user_id', $user->id)->first();
        return $userInterest ? $userInterest->is_primary : false;
    }

    public function addToUser(User $user, bool $isPrimary = false): bool
    {
        if ($this->users()->where('user_id', $user->id)->exists()) {
            return false;
        }

        $this->users()->attach($user->id, [
            'is_primary' => $isPrimary,
            'added_at' => now(),
        ]);

        return true;
    }

    public function removeFromUser(User $user): bool
    {
        $this->users()->updateExistingPivot($user->id, [
            'removed_at' => now(),
        ]);

        return true;
    }

    public function setAsPrimaryForUser(User $user): bool
    {
        // Remove primary from other interests in same category
        $this->userInterests()
            ->where('user_id', $user->id)
            ->where('interest_id', '!=', $this->id)
            ->where('category', $this->category)
            ->update(['is_primary' => false]);

        // Set this as primary
        $this->users()->updateExistingPivot($user->id, [
            'is_primary' => true,
        ]);

        return true;
    }

    public function getCompatibleUsers(User $user, int $limit = 10): array
    {
        return $this->users()
            ->where('user_id', '!=', $user->id)
            ->whereHas('preferences', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getCompatibilityScore(User $user1, User $user2): float
    {
        $user1Interests = $user1->interests()->pluck('interests.id')->toArray();
        $user2Interests = $user2->interests()->pluck('interests.id')->toArray();

        $commonInterests = array_intersect($user1Interests, $user2Interests);
        $totalInterests = array_unique(array_merge($user1Interests, $user2Interests));

        if (empty($totalInterests)) {
            return 0.0;
        }

        return round((count($commonInterests) / count($totalInterests)) * 100, 2);
    }

    public function getPopularityScore(): float
    {
        $totalUsers = User::count();
        $interestUsers = $this->getUserCount();

        if ($totalUsers === 0) {
            return 0.0;
        }

        return round(($interestUsers / $totalUsers) * 100, 2);
    }

    public function getTrendingScore(): float
    {
        $recentUsers = $this->users()
            ->wherePivot('added_at', '>=', now()->subDays(7))
            ->count();

        $totalUsers = $this->getUserCount();

        if ($totalUsers === 0) {
            return 0.0;
        }

        return round(($recentUsers / $totalUsers) * 100, 2);
    }

    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    public function feature(): bool
    {
        return $this->update(['is_featured' => true]);
    }

    public function unfeature(): bool
    {
        return $this->update(['is_featured' => false]);
    }

    public function softDelete(User $user): bool
    {
        return $this->update([
            'deleted_at' => now(),
            'deleted_by' => $user->id,
        ]);
    }

    public function restore(): bool
    {
        return $this->update([
            'deleted_at' => null,
            'deleted_by' => null,
        ]);
    }

    public function updateSortOrder(int $order): bool
    {
        return $this->update(['sort_order' => $order]);
    }

    public function getIconUrl(): ?string
    {
        if (!$this->icon) {
            return null;
        }

        if (filter_var($this->icon, FILTER_VALIDATE_URL)) {
            return $this->icon;
        }

        return asset('storage/interests/' . $this->icon);
    }

    public function getColorWithFallback(): string
    {
        return $this->color ?? '#6B7280';
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
    public static function getCategories(): array
    {
        return [
            self::CATEGORY_HOBBIES => 'Hobbies',
            self::CATEGORY_SPORTS => 'Sports',
            self::CATEGORY_MUSIC => 'Music',
            self::CATEGORY_MOVIES => 'Movies',
            self::CATEGORY_BOOKS => 'Books',
            self::CATEGORY_TRAVEL => 'Travel',
            self::CATEGORY_FOOD => 'Food',
            self::CATEGORY_TECHNOLOGY => 'Technology',
            self::CATEGORY_ART => 'Art',
            self::CATEGORY_FASHION => 'Fashion',
            self::CATEGORY_FITNESS => 'Fitness',
            self::CATEGORY_NATURE => 'Nature',
            self::CATEGORY_BUSINESS => 'Business',
            self::CATEGORY_EDUCATION => 'Education',
            self::CATEGORY_VOLUNTEERING => 'Volunteering',
            self::CATEGORY_GAMING => 'Gaming',
            self::CATEGORY_PHOTOGRAPHY => 'Photography',
            self::CATEGORY_DANCE => 'Dance',
            self::CATEGORY_COOKING => 'Cooking',
            self::CATEGORY_GARDENING => 'Gardening',
            self::CATEGORY_PETS => 'Pets',
            self::CATEGORY_MEDITATION => 'Meditation',
            self::CATEGORY_YOGA => 'Yoga',
            self::CATEGORY_READING => 'Reading',
            self::CATEGORY_WRITING => 'Writing',
            self::CATEGORY_PAINTING => 'Painting',
            self::CATEGORY_CRAFTING => 'Crafting',
            self::CATEGORY_COLLECTING => 'Collecting',
            self::CATEGORY_OTHER => 'Other',
        ];
    }

    public static function getPopularInterests(int $limit = 10): array
    {
        return self::popular($limit)->get()->toArray();
    }

    public static function getFeaturedInterests(): array
    {
        return self::featured()->ordered()->get()->toArray();
    }

    public static function getInterestsByCategory(string $category): array
    {
        return self::byCategory($category)->active()->ordered()->get()->toArray();
    }

    public static function searchInterests(string $search, int $limit = 20): array
    {
        return self::search($search)->active()->ordered()->limit($limit)->get()->toArray();
    }

    public static function getCompatibleInterests(User $user, int $limit = 10): array
    {
        return self::compatibleWith($user)->active()->popular($limit)->get()->toArray();
    }

    public static function createInterest(
        string $name,
        string $category,
        string $description = null,
        string $subcategory = null,
        string $icon = null,
        string $color = null,
        bool $isActive = true,
        bool $isFeatured = false,
        int $sortOrder = 0,
        array $metadata = []
    ): self {
        return self::create([
            'name' => $name,
            'description' => $description,
            'category' => $category,
            'subcategory' => $subcategory,
            'icon' => $icon,
            'color' => $color,
            'is_active' => $isActive,
            'is_featured' => $isFeatured,
            'sort_order' => $sortOrder,
            'metadata' => $metadata,
        ]);
    }

    public static function getDefaultInterests(): array
    {
        return [
            ['name' => 'Reading', 'category' => self::CATEGORY_READING, 'icon' => '📚'],
            ['name' => 'Travel', 'category' => self::CATEGORY_TRAVEL, 'icon' => '✈️'],
            ['name' => 'Cooking', 'category' => self::CATEGORY_COOKING, 'icon' => '👨‍🍳'],
            ['name' => 'Photography', 'category' => self::CATEGORY_PHOTOGRAPHY, 'icon' => '📸'],
            ['name' => 'Music', 'category' => self::CATEGORY_MUSIC, 'icon' => '🎵'],
            ['name' => 'Fitness', 'category' => self::CATEGORY_FITNESS, 'icon' => '💪'],
            ['name' => 'Movies', 'category' => self::CATEGORY_MOVIES, 'icon' => '🎬'],
            ['name' => 'Gaming', 'category' => self::CATEGORY_GAMING, 'icon' => '🎮'],
            ['name' => 'Dance', 'category' => self::CATEGORY_DANCE, 'icon' => '💃'],
            ['name' => 'Art', 'category' => self::CATEGORY_ART, 'icon' => '🎨'],
            ['name' => 'Technology', 'category' => self::CATEGORY_TECHNOLOGY, 'icon' => '💻'],
            ['name' => 'Nature', 'category' => self::CATEGORY_NATURE, 'icon' => '🌿'],
            ['name' => 'Pets', 'category' => self::CATEGORY_PETS, 'icon' => '🐕'],
            ['name' => 'Yoga', 'category' => self::CATEGORY_YOGA, 'icon' => '🧘'],
            ['name' => 'Volunteering', 'category' => self::CATEGORY_VOLUNTEERING, 'icon' => '🤝'],
        ];
    }
}
