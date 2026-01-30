<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additional indexes for production performance optimization.
     * These indexes support common query patterns for search and filtering.
     */
    public function up(): void
    {
        // Users table - additional indexes for search and activity
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'date_of_birth') && Schema::hasColumn('users', 'gender') && !$this->indexExists('users', 'users_age_gender_status_idx')) {
                    $table->index(['date_of_birth', 'gender', 'status'], 'users_age_gender_status_idx');
                }
                if (Schema::hasColumn('users', 'last_active_at') && !$this->indexExists('users', 'users_status_activity_idx')) {
                    $table->index(['status', 'last_active_at'], 'users_status_activity_idx');
                }
                if (Schema::hasColumn('users', 'is_premium') && Schema::hasColumn('users', 'last_active_at') && !$this->indexExists('users', 'users_premium_active_idx')) {
                    $table->index(['is_premium', 'status', 'last_active_at'], 'users_premium_active_idx');
                }
            });
        }

        // User profiles - additional search indexes
        if (Schema::hasTable('user_profiles')) {
            Schema::table('user_profiles', function (Blueprint $table) {
                if (Schema::hasColumn('user_profiles', 'current_country') && !$this->indexExists('user_profiles', 'profiles_location_full_idx')) {
                    $table->index(['current_country', 'current_state', 'current_city'], 'profiles_location_full_idx');
                }
                if (Schema::hasColumn('user_profiles', 'religion') && !$this->indexExists('user_profiles', 'profiles_religion_marital_verified_idx')) {
                    $table->index(['religion', 'marital_status', 'profile_verified'], 'profiles_religion_marital_verified_idx');
                }
                if (Schema::hasColumn('user_profiles', 'annual_income_usd') && !$this->indexExists('user_profiles', 'profiles_income_education_idx')) {
                    $table->index(['annual_income_usd', 'education_level'], 'profiles_income_education_idx');
                }
                if (Schema::hasColumn('user_profiles', 'latitude') && !$this->indexExists('user_profiles', 'profiles_geolocation_idx')) {
                    $table->index(['latitude', 'longitude'], 'profiles_geolocation_idx');
                }
            });
        }

        // Conversations - for chat list performance
        if (Schema::hasTable('conversations')) {
            Schema::table('conversations', function (Blueprint $table) {
                if (!$this->indexExists('conversations', 'conversations_updated_at_index')) {
                    $table->index('updated_at', 'conversations_updated_at_index');
                }
            });
        }

        // Subscriptions - for billing queries
        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                if (Schema::hasColumn('subscriptions', 'end_date') && !$this->indexExists('subscriptions', 'subscriptions_status_end_date_idx')) {
                    $table->index(['status', 'end_date'], 'subscriptions_status_end_date_idx');
                }
                if (Schema::hasColumn('subscriptions', 'end_date') && !$this->indexExists('subscriptions', 'subscriptions_user_status_end_idx')) {
                    $table->index(['user_id', 'status', 'end_date'], 'subscriptions_user_status_end_idx');
                }
            });
        }

        // User photos - for gallery performance
        if (Schema::hasTable('user_photos')) {
            Schema::table('user_photos', function (Blueprint $table) {
                if (Schema::hasColumn('user_photos', 'is_primary') && !$this->indexExists('user_photos', 'photos_user_primary_order_idx')) {
                    $table->index(['user_id', 'is_primary', 'created_at'], 'photos_user_primary_order_idx');
                }
                if (Schema::hasColumn('user_photos', 'is_approved') && !$this->indexExists('user_photos', 'photos_approval_queue_idx')) {
                    $table->index(['is_approved', 'created_at'], 'photos_approval_queue_idx');
                }
            });
        }

        // Reports - for admin moderation
        if (Schema::hasTable('reports')) {
            Schema::table('reports', function (Blueprint $table) {
                if (!$this->indexExists('reports', 'reports_status_date_idx')) {
                    $table->index(['status', 'created_at'], 'reports_status_date_idx');
                }
                if (Schema::hasColumn('reports', 'reported_user_id') && !$this->indexExists('reports', 'reports_user_status_idx')) {
                    $table->index(['reported_user_id', 'status'], 'reports_user_status_idx');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'users_age_gender_status_idx');
                $this->dropIndexIfExists($table, 'users_status_activity_idx');
                $this->dropIndexIfExists($table, 'users_premium_active_idx');
            });
        }

        if (Schema::hasTable('user_profiles')) {
            Schema::table('user_profiles', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'profiles_location_full_idx');
                $this->dropIndexIfExists($table, 'profiles_religion_marital_verified_idx');
                $this->dropIndexIfExists($table, 'profiles_income_education_idx');
                $this->dropIndexIfExists($table, 'profiles_geolocation_idx');
            });
        }

        if (Schema::hasTable('conversations')) {
            Schema::table('conversations', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'conversations_updated_at_index');
            });
        }

        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'subscriptions_status_end_date_idx');
                $this->dropIndexIfExists($table, 'subscriptions_user_status_end_idx');
            });
        }

        if (Schema::hasTable('user_photos')) {
            Schema::table('user_photos', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'photos_user_primary_order_idx');
                $this->dropIndexIfExists($table, 'photos_approval_queue_idx');
            });
        }

        if (Schema::hasTable('reports')) {
            Schema::table('reports', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'reports_status_date_idx');
                $this->dropIndexIfExists($table, 'reports_user_status_idx');
            });
        }
    }

    /**
     * Check if an index exists on a table (MySQL compatible).
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }

    /**
     * Drop an index if it exists.
     */
    private function dropIndexIfExists(Blueprint $table, string $indexName): void
    {
        try {
            $table->dropIndex($indexName);
        } catch (\Exception $e) {
            // Index doesn't exist, ignore
        }
    }
};
