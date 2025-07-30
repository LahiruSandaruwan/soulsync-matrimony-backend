<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Notification;
use App\Models\UserPhoto;
use App\Models\Report;
use App\Models\Interest;
use Laravel\Sanctum\Sanctum;

class AdminApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $admin;
    protected User $moderator;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles manually
        $adminRole = \Spatie\Permission\Models\Role::create(['name' => 'admin']);
        $moderatorRole = \Spatie\Permission\Models\Role::create(['name' => 'moderator']);
        
        // Create users and assign roles
        $this->admin = User::factory()->create([
            'email_verified_at' => now(),
            'profile_status' => 'approved',
            'is_admin' => true
        ]);
        $this->admin->assignRole($adminRole);
        
        $this->moderator = User::factory()->create([
            'email_verified_at' => now(),
            'profile_status' => 'approved',
            'is_moderator' => true
        ]);
        $this->moderator->assignRole($moderatorRole);
        
        $this->regularUser = User::factory()->create([
            'email_verified_at' => now(),
            'profile_status' => 'approved'
        ]);
    }

    /** @test */
    public function admin_can_access_dashboard()
    {
        Sanctum::actingAs($this->admin);
        
        $response = $this->getJson('/api/v1/admin/dashboard');
        
        // For now, just test that the endpoint exists and returns a response
        // The actual role-based access will be tested when middleware is properly configured
        $response->assertStatus(200);
    }

    /** @test */
    public function admin_can_get_dashboard_stats()
    {
        Sanctum::actingAs($this->admin);
        
        $response = $this->getJson('/api/v1/admin/stats');
        
        // For now, just test that the endpoint exists and returns a response
        $response->assertStatus(200);
    }

    /** @test */
    public function moderator_can_access_dashboard()
    {
        Sanctum::actingAs($this->moderator);
        
        $response = $this->getJson('/api/v1/admin/dashboard');
        
        // For now, just test that the endpoint exists and returns a response
        $response->assertStatus(200);
    }

    /** @test */
    public function regular_user_cannot_access_admin_dashboard()
    {
        Sanctum::actingAs($this->regularUser);
        
        $response = $this->getJson('/api/v1/admin/dashboard');
        
        // Regular users should be denied access to admin routes
        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_get_users_list()
    {
        Sanctum::actingAs($this->admin);
        
        // Create some users
        User::factory()->count(5)->create();
        
        $response = $this->getJson('/api/v1/admin/users');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'first_name',
                            'last_name',
                            'email',
                            'status',
                            'profile_status',
                            'created_at'
                        ]
                    ],
                    'meta' => [
                        'current_page',
                        'total',
                        'per_page'
                    ]
                ]);
    }

    /** @test */
    public function admin_can_get_specific_user()
    {
        Sanctum::actingAs($this->admin);
        
        $user = User::factory()->create();
        
        $response = $this->getJson("/api/v1/admin/users/{$user->id}");
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'first_name',
                        'last_name',
                        'email',
                        'profile',
                        'preferences',
                        'photos',
                        'subscriptions'
                    ]
                ]);
    }

    /** @test */
    public function admin_can_update_user_status()
    {
        Sanctum::actingAs($this->admin);
        
        $user = User::factory()->create(['status' => 'active']);
        
        $response = $this->putJson("/api/v1/admin/users/{$user->id}/status", [
            'status' => 'suspended',
            'reason' => 'Violation of terms'
        ]);
        
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
        
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 'suspended'
        ]);
    }

    /** @test */
    public function admin_can_update_user_profile_status()
    {
        Sanctum::actingAs($this->admin);
        
        $user = User::factory()->create(['profile_status' => 'pending']);
        
        $response = $this->putJson("/api/v1/admin/users/{$user->id}/profile-status", [
            'profile_status' => 'approved',
            'notes' => 'Profile looks good'
        ]);
        
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
        
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'profile_status' => 'approved'
        ]);
    }

    /** @test */
    public function admin_can_suspend_user()
    {
        Sanctum::actingAs($this->admin);
        
        $user = User::factory()->create(['status' => 'active']);
        
        $response = $this->postJson("/api/v1/admin/users/{$user->id}/suspend", [
            'reason' => 'Inappropriate behavior',
            'duration_days' => 7
        ]);
        
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function admin_can_ban_user()
    {
        Sanctum::actingAs($this->admin);
        
        $user = User::factory()->create(['status' => 'active']);
        
        $response = $this->postJson("/api/v1/admin/users/{$user->id}/ban", [
            'reason' => 'Serious violation'
        ]);
        
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
        
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 'banned'
        ]);
    }

    /** @test */
    public function admin_can_unban_user()
    {
        Sanctum::actingAs($this->admin);
        
        $user = User::factory()->create(['status' => 'banned']);
        
        $response = $this->postJson("/api/v1/admin/users/{$user->id}/unban", [
            'reason' => 'User appeal approved'
        ]);
        
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
        
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 'active'
        ]);
    }

    /** @test */
    public function admin_can_delete_user()
    {
        Sanctum::actingAs($this->admin);
        
        $user = User::factory()->create();
        
        $response = $this->deleteJson("/api/v1/admin/users/{$user->id}", [
            'reason' => 'User requested deletion',
            'confirmation' => 'DELETE_USER'
        ]);
        
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
        
        $this->assertDatabaseMissing('users', [
            'id' => $user->id
        ]);
    }

    /** @test */
    public function admin_can_get_pending_photos()
    {
        Sanctum::actingAs($this->admin);
        
        // Create pending photos
        UserPhoto::factory()->count(3)->create(['status' => 'pending']);
        UserPhoto::factory()->count(2)->create(['status' => 'approved']);
        
        $response = $this->getJson('/api/v1/admin/photos/pending');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'user',
                                'file_path',
                                'status'
                            ]
                        ]
                    ]
                ]);
    }

    /** @test */
    public function admin_can_approve_photo()
    {
        Sanctum::actingAs($this->admin);
        
        $photo = UserPhoto::factory()->create(['status' => 'pending']);
        
        $response = $this->postJson("/api/v1/admin/photos/{$photo->id}/approve", [
            'notes' => 'Photo approved'
        ]);
        
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
        
        $this->assertDatabaseHas('user_photos', [
            'id' => $photo->id,
            'status' => 'approved'
        ]);
    }

    /** @test */
    public function admin_can_reject_photo()
    {
        Sanctum::actingAs($this->admin);
        
        $photo = UserPhoto::factory()->create(['status' => 'pending']);
        
        $response = $this->postJson("/api/v1/admin/photos/{$photo->id}/reject", [
            'reason' => 'Inappropriate content',
            'notes' => 'Photo violates community guidelines'
        ]);
        
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
        
        $this->assertDatabaseHas('user_photos', [
            'id' => $photo->id,
            'status' => 'rejected'
        ]);
    }

    /** @test */
    public function admin_can_get_reports_list()
    {
        Sanctum::actingAs($this->admin);
        
        // Create some reports
        Report::factory()->count(5)->create();
        
        $response = $this->getJson('/api/v1/admin/reports');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'type',
                            'status',
                            'reporter',
                            'reported_user',
                            'created_at'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function admin_can_get_specific_report()
    {
        Sanctum::actingAs($this->admin);
        
        $report = Report::factory()->create();
        
        $response = $this->getJson("/api/v1/admin/reports/{$report->id}");
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'type',
                        'status',
                        'description',
                        'evidence',
                        'reporter',
                        'reported_user',
                        'created_at'
                    ]
                ]);
    }

    /** @test */
    public function admin_can_update_report_status()
    {
        Sanctum::actingAs($this->admin);
        
        $report = Report::factory()->create(['status' => 'pending']);
        
        $response = $this->putJson("/api/v1/admin/reports/{$report->id}/status", [
            'status' => 'under_review',
            'moderator_notes' => 'Under investigation'
        ]);
        
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
        
        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => 'under_review'
        ]);
    }

    /** @test */
    public function admin_can_take_action_on_report()
    {
        Sanctum::actingAs($this->admin);
        
        $report = Report::factory()->create(['status' => 'under_review']);
        
        $response = $this->postJson("/api/v1/admin/reports/{$report->id}/action", [
            'action' => 'warn',
            'reason' => 'Warning issued for inappropriate behavior'
        ]);
        
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function admin_can_manage_interests()
    {
        Sanctum::actingAs($this->admin);
        
        $response = $this->getJson('/api/v1/admin/content/interests');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'name',
                                'category',
                                'is_active',
                                'user_count'
                            ]
                        ]
                    ]
                ]);
    }

    /** @test */
    public function admin_can_create_interest()
    {
        Sanctum::actingAs($this->admin);
        
        $interestData = [
            'name' => 'New Hobby',
            'category' => 'hobbies',
            'description' => 'A new interest category',
            'is_active' => true
        ];
        
        $response = $this->postJson('/api/v1/admin/content/interests', $interestData);
        
        $response->assertStatus(201)
                ->assertJson(['success' => true]);
        
        $this->assertDatabaseHas('interests', [
            'name' => 'New Hobby',
            'category' => 'hobbies'
        ]);
    }

    /** @test */
    public function admin_can_update_interest()
    {
        Sanctum::actingAs($this->admin);
        
        $interest = Interest::factory()->create();
        
        $updateData = [
            'name' => 'Updated Interest',
            'description' => 'Updated description'
        ];
        
        $response = $this->putJson("/api/v1/admin/content/interests/{$interest->id}", $updateData);
        
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
        
        $this->assertDatabaseHas('interests', [
            'id' => $interest->id,
            'name' => 'Updated Interest'
        ]);
    }

    /** @test */
    public function admin_can_delete_interest()
    {
        Sanctum::actingAs($this->admin);
        
        $interest = Interest::factory()->create();
        
        $response = $this->deleteJson("/api/v1/admin/content/interests/{$interest->id}");
        
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
        
        $this->assertDatabaseMissing('interests', [
            'id' => $interest->id
        ]);
    }

    /** @test */
    public function admin_can_get_system_settings()
    {
        Sanctum::actingAs($this->admin);
        
        $response = $this->getJson('/api/v1/admin/settings');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'general',
                        'matching',
                        'notification',
                        'payment',
                        'security'
                    ]
                ]);
    }

    /** @test */
    public function admin_can_update_system_settings()
    {
        Sanctum::actingAs($this->admin);
        
        $settingsData = [
            'site_name' => 'Updated Site Name',
            'maintenance_mode' => false,
            'max_daily_matches' => 20,
            'compatibility_threshold' => 70
        ];
        
        $response = $this->putJson('/api/v1/admin/settings', [
            'category' => 'general',
            'settings' => $settingsData
        ]);
        
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function moderator_can_moderate_photos()
    {
        Sanctum::actingAs($this->moderator);
        
        $photo = UserPhoto::factory()->create(['status' => 'pending']);
        
        $response = $this->postJson("/api/v1/admin/photos/{$photo->id}/approve");
        
        $response->assertStatus(200);
    }

    /** @test */
    public function moderator_cannot_manage_users()
    {
        Sanctum::actingAs($this->moderator);
        
        $response = $this->getJson('/api/v1/admin/users');
        
        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_get_user_analytics()
    {
        Sanctum::actingAs($this->admin);
        
        $response = $this->getJson('/api/v1/admin/users/analytics');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'registration_trends',
                        'active_users',
                        'premium_conversion',
                        'geographic_distribution'
                    ]
                ]);
    }

    /** @test */
    public function admin_can_get_revenue_analytics()
    {
        Sanctum::actingAs($this->admin);
        
        $response = $this->getJson('/api/v1/admin/revenue/analytics');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'total_revenue',
                        'monthly_revenue',
                        'subscription_stats',
                        'payment_methods'
                    ]
                ]);
    }

    /** @test */
    public function admin_can_export_user_data()
    {
        Sanctum::actingAs($this->admin);
        
        $response = $this->postJson('/api/v1/admin/users/export', [
            'format' => 'csv',
            'filters' => [
                'status' => 'active',
                'date_range' => 'last_30_days'
            ]
        ]);
        
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function admin_can_bulk_action_on_users()
    {
        Sanctum::actingAs($this->admin);
        
        $users = User::factory()->count(3)->create();
        $userIds = $users->pluck('id')->toArray();
        
        $response = $this->postJson('/api/v1/admin/users/bulk-action', [
            'user_ids' => $userIds,
            'action' => 'suspend',
            'reason' => 'Bulk suspension'
        ]);
        
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function admin_can_get_system_health()
    {
        Sanctum::actingAs($this->admin);
        
        $response = $this->getJson('/api/v1/admin/system/health');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'overall_status',
                        'checks' => [
                            'database',
                            'cache',
                            'storage',
                            'email',
                            'queue',
                            'memory',
                            'disk_space'
                        ],
                        'checked_at'
                    ]
                ]);
    }
} 