<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavedSearch extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'filters',
        'is_alert_enabled',
        'alert_frequency',
        'last_alert_sent',
        'result_count',
        'last_executed',
        'is_active',
    ];

    protected $casts = [
        'filters' => 'array',
        'is_alert_enabled' => 'boolean',
        'last_alert_sent' => 'datetime',
        'last_executed' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user that owns the saved search
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the search alerts for this saved search
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(SearchAlert::class);
    }

    /**
     * Execute the saved search and return results
     */
    public function execute(): array
    {
        $filters = $this->filters;
        
        // Build query based on saved filters
        $query = User::query()
            ->where('status', 'active')
            ->whereNot('id', $this->user_id);

        // Apply age filter
        if (isset($filters['age_min']) || isset($filters['age_max'])) {
            if (isset($filters['age_min'])) {
                $query->whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ?', [$filters['age_min']]);
            }
            if (isset($filters['age_max'])) {
                $query->whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) <= ?', [$filters['age_max']]);
            }
        }

        // Apply gender filter
        if (isset($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        // Apply location filters
        if (isset($filters['country'])) {
            $query->where('country', $filters['country']);
        }

        if (isset($filters['state'])) {
            $query->where('state', $filters['state']);
        }

        if (isset($filters['city'])) {
            $query->where('city', $filters['city']);
        }

        // Apply profile-based filters
        if (isset($filters['religion']) || isset($filters['education']) || isset($filters['occupation'])) {
            $query->whereHas('profile', function ($profileQuery) use ($filters) {
                if (isset($filters['religion'])) {
                    $profileQuery->where('religion', $filters['religion']);
                }
                if (isset($filters['education'])) {
                    $profileQuery->where('education_level', $filters['education']);
                }
                if (isset($filters['occupation'])) {
                    $profileQuery->where('occupation', 'like', '%' . $filters['occupation'] . '%');
                }
            });
        }

        // Apply preference filters
        if (isset($filters['marital_status'])) {
            $query->whereHas('profile', function ($profileQuery) use ($filters) {
                $profileQuery->where('marital_status', $filters['marital_status']);
            });
        }

        // Apply interests filter
        if (isset($filters['interests']) && is_array($filters['interests'])) {
            $query->whereHas('interests', function ($interestQuery) use ($filters) {
                $interestQuery->whereIn('name', $filters['interests']);
            });
        }

        // Apply premium filter
        if (isset($filters['premium_only']) && $filters['premium_only']) {
            $query->where('is_premium_active', true);
        }

        // Apply photo filter
        if (isset($filters['with_photos']) && $filters['with_photos']) {
            $query->whereHas('photos');
        }

        // Apply online status filter
        if (isset($filters['online_only']) && $filters['online_only']) {
            $query->where('last_seen_at', '>=', now()->subMinutes(30));
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'last_active';
        switch ($sortBy) {
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            case 'age_asc':
                $query->orderBy('date_of_birth', 'desc');
                break;
            case 'age_desc':
                $query->orderBy('date_of_birth', 'asc');
                break;
            case 'distance':
                // Distance-based sorting using Haversine formula
                $userLat = $this->user->profile->latitude ?? 0;
                $userLng = $this->user->profile->longitude ?? 0;
                
                if ($userLat && $userLng) {
                    $query->selectRaw("
                        users.*,
                        (6371 * acos(cos(radians(?)) * cos(radians(COALESCE(user_profiles.latitude, 0))) * 
                        cos(radians(COALESCE(user_profiles.longitude, 0)) - radians(?)) + 
                        sin(radians(?)) * sin(radians(COALESCE(user_profiles.latitude, 0))))) as distance
                    ", [$userLat, $userLng, $userLat])
                    ->orderBy('distance', 'asc');
                } else {
                    // Fallback to city-based sorting if GPS not available
                    $userCity = $this->user->profile->city ?? '';
                    if ($userCity) {
                        $query->orderByRaw("CASE WHEN user_profiles.city = ? THEN 0 ELSE 1 END, last_seen_at DESC", [$userCity]);
                    } else {
                        $query->orderBy('last_seen_at', 'desc');
                    }
                }
                break;
            default:
                $query->orderBy('last_seen_at', 'desc');
        }

        // Execute query with pagination
        $limit = $filters['limit'] ?? 50;
        $results = $query->with(['profile', 'photos'])->limit($limit)->get();

        // Update search statistics
        $this->update([
            'result_count' => $results->count(),
            'last_executed' => now(),
        ]);

        return [
            'results' => $results,
            'total_count' => $results->count(),
            'filters_applied' => $filters,
            'executed_at' => now(),
        ];
    }

    /**
     * Check for new results since last alert
     */
    public function checkForNewResults(): int
    {
        $currentResults = $this->execute();
        $currentCount = $currentResults['total_count'];
        
        $previousCount = $this->result_count;
        $newResultsCount = max(0, $currentCount - $previousCount);

        if ($newResultsCount > 0 && $this->is_alert_enabled) {
            $this->createAlert($newResultsCount, array_slice($currentResults['results']->toArray(), 0, 3));
        }

        return $newResultsCount;
    }

    /**
     * Create a search alert
     */
    private function createAlert(int $newResultsCount, array $sampleResults): void
    {
        SearchAlert::create([
            'saved_search_id' => $this->id,
            'user_id' => $this->user_id,
            'new_results_count' => $newResultsCount,
            'sample_results' => $sampleResults,
        ]);
    }

    /**
     * Check if alert should be sent
     */
    public function shouldSendAlert(): bool
    {
        if (!$this->is_alert_enabled) {
            return false;
        }

        if (!$this->last_alert_sent) {
            return true;
        }

        $hoursSinceLastAlert = $this->last_alert_sent->diffInHours(now());
        return $hoursSinceLastAlert >= $this->alert_frequency;
    }

    /**
     * Send alert notification
     */
    public function sendAlert(): bool
    {
        if (!$this->shouldSendAlert()) {
            return false;
        }

        $newResultsCount = $this->checkForNewResults();
        
        if ($newResultsCount > 0) {
            // Send notification
            $this->user->notifications()->create([
                'type' => 'search_alert',
                'title' => 'New Search Results',
                'content' => "Your saved search '{$this->name}' has {$newResultsCount} new results!",
                'data' => [
                    'saved_search_id' => $this->id,
                    'new_results_count' => $newResultsCount,
                ],
            ]);

            // Update last alert sent
            $this->update(['last_alert_sent' => now()]);

            return true;
        }

        return false;
    }

    /**
     * Get popular search filters
     */
    public static function getPopularFilters(): array
    {
        $searches = static::where('is_active', true)->get();
        $allFilters = [];

        foreach ($searches as $search) {
            foreach ($search->filters as $key => $value) {
                if (!isset($allFilters[$key])) {
                    $allFilters[$key] = [];
                }
                
                if (is_array($value)) {
                    foreach ($value as $item) {
                        $allFilters[$key][] = $item;
                    }
                } else {
                    $allFilters[$key][] = $value;
                }
            }
        }

        // Count and sort by popularity
        $popularFilters = [];
        foreach ($allFilters as $key => $values) {
            $popularFilters[$key] = array_count_values($values);
            arsort($popularFilters[$key]);
        }

        return $popularFilters;
    }

    /**
     * Get user's search history
     */
    public static function getUserSearchHistory(User $user, int $limit = 10): array
    {
        return static::where('user_id', $user->id)
            ->orderBy('last_executed', 'desc')
            ->limit($limit)
            ->get(['name', 'filters', 'result_count', 'last_executed'])
            ->toArray();
    }

    /**
     * Cleanup old inactive searches
     */
    public static function cleanup(int $daysOld = 90): int
    {
        return static::where('is_active', false)
            ->where('updated_at', '<', now()->subDays($daysOld))
            ->delete();
    }

    /**
     * Get search analytics
     */
    public static function getAnalytics(): array
    {
        return [
            'total_saved_searches' => static::count(),
            'active_searches' => static::where('is_active', true)->count(),
            'searches_with_alerts' => static::where('is_alert_enabled', true)->count(),
            'average_results_per_search' => static::avg('result_count'),
            'most_popular_filters' => static::getPopularFilters(),
            'searches_executed_today' => static::whereDate('last_executed', today())->count(),
        ];
    }
} 