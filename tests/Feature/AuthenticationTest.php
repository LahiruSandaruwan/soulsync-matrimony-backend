<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test user registration with valid data
     */
    public function test_user_can_register_with_valid_data()
    {
        $userData = [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email' => $this->faker->unique()->safeEmail,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'date_of_birth' => $this->faker->date('Y-m-d', '2000-01-01'),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'phone' => $this->faker->phoneNumber,
            'country_code' => 'LK',
            'terms_accepted' => true,
            'privacy_accepted' => true,
        ];

        $response = $this->postJson('/api/v1/auth/register', $userData);

        if ($response->status() !== 201) {
            dump($response->json());
        }
        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'user' => [
                             'id',
                             'first_name',
                             'last_name',
                             'email',
                         ],
                         'token',
                         'next_step',
                     ]
                 ]);

        $this->assertDatabaseHas('users', [
            'email' => $userData['email'],
            'first_name' => $userData['first_name'],
            'last_name' => $userData['last_name'],
        ]);
    }

    /**
     * Test registration fails with invalid email
     */
    public function test_registration_fails_with_invalid_email()
    {
        $userData = [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email' => 'invalid-email',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'date_of_birth' => $this->faker->date('Y-m-d', '2000-01-01'),
            'gender' => 'male',
            'terms_accepted' => true,
            'privacy_accepted' => true,
        ];

        $response = $this->postJson('/api/v1/auth/register', $userData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test registration fails with duplicate email
     */
    public function test_registration_fails_with_duplicate_email()
    {
        $existingUser = User::factory()->create();

        $userData = [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email' => $existingUser->email,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'date_of_birth' => $this->faker->date('Y-m-d', '2000-01-01'),
            'gender' => 'male',
            'terms_accepted' => true,
            'privacy_accepted' => true,
        ];

        $response = $this->postJson('/api/v1/auth/register', $userData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test registration fails with weak password
     */
    public function test_registration_fails_with_weak_password()
    {
        $userData = [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email' => $this->faker->unique()->safeEmail,
            'password' => '123',
            'password_confirmation' => '123',
            'date_of_birth' => $this->faker->date('Y-m-d', '2000-01-01'),
            'gender' => 'male',
            'terms_accepted' => true,
            'privacy_accepted' => true,
        ];

        $response = $this->postJson('/api/v1/auth/register', $userData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['password']);
    }

    /**
     * Test user can login with valid credentials
     */
    public function test_user_can_login_with_valid_credentials()
    {
        $password = 'Password123!';
        $user = User::factory()->create([
            'password' => Hash::make($password),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'user' => [
                             'id',
                             'first_name',
                             'last_name',
                             'email',
                         ],
                         'token',
                         'next_step',
                     ]
                 ]);
    }

    /**
     * Test login fails with invalid password
     */
    public function test_login_fails_with_invalid_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('correctpassword'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Invalid credentials',
                 ]);
    }

    /**
     * Test login fails with non-existent email
     */
    public function test_login_fails_with_non_existent_email()
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Invalid credentials',
                 ]);
    }

    /**
     * Test login fails for inactive user
     */
    public function test_login_fails_for_inactive_user()
    {
        $password = 'Password123!';
        $user = User::factory()->create([
            'password' => Hash::make($password),
            'status' => 'inactive',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertStatus(403)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Account is not active. Please contact support.',
                 ]);
    }

    /**
     * Test authenticated user can access profile
     */
    public function test_authenticated_user_can_access_profile()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'user' => [
                             'id',
                             'first_name',
                             'last_name',
                             'email',
                         ]
                     ]
                 ]);
    }

    /**
     * Test unauthenticated user cannot access profile
     */
    public function test_unauthenticated_user_cannot_access_profile()
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    /**
     * Test user can logout
     */
    public function test_user_can_logout()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Logged out successfully',
                 ]);
    }

    /**
     * Test user can logout from all devices
     */
    public function test_user_can_logout_from_all_devices()
    {
        $user = User::factory()->create();
        
        // Create multiple tokens (simulate multiple devices)
        $token1 = $user->createToken('device1')->plainTextToken;
        $token2 = $user->createToken('device2')->plainTextToken;

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/logout-all');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Logged out from all devices successfully',
                 ]);

        // Verify all tokens are revoked
        $this->assertEquals(0, $user->tokens()->count());
    }

    /**
     * Test forgot password with valid email
     */
    public function test_forgot_password_with_valid_email()
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Password reset link sent to your email',
                 ]);
    }

    /**
     * Test forgot password with invalid email
     */
    public function test_forgot_password_with_invalid_email()
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(404)
                 ->assertJson([
                     'success' => false,
                     'message' => 'No user found with this email address',
                 ]);
    }

    /**
     * Test change password with valid current password
     */
    public function test_change_password_with_valid_current_password()
    {
        $currentPassword = 'CurrentPassword123!';
        $newPassword = 'NewPassword123!';
        
        $user = User::factory()->create([
            'password' => Hash::make($currentPassword),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/change-password', [
            'current_password' => $currentPassword,
            'new_password' => $newPassword,
            'new_password_confirmation' => $newPassword,
            'password' => $currentPassword,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Password changed successfully',
                 ]);

        // Verify password was actually changed
        $user->refresh();
        $this->assertTrue(Hash::check($newPassword, $user->password));
    }

    /**
     * Test change password fails with incorrect current password
     */
    public function test_change_password_fails_with_incorrect_current_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('CurrentPassword123!'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'WrongPassword123!',
            'new_password' => 'NewPassword123!',
            'new_password_confirmation' => 'NewPassword123!',
            'password' => 'WrongPassword123!',
        ]);

        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Current password is incorrect',
                 ]);
    }

    /**
     * Test account deletion with correct password
     */
    public function test_account_deletion_with_correct_password()
    {
        $password = 'Password123!';
        $user = User::factory()->create([
            'password' => Hash::make($password),
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/v1/auth/delete-account', [
            'password' => $password,
            'confirmation' => 'DELETE_MY_ACCOUNT',
            'reason' => 'Testing account deletion',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => "Account deleted successfully. We're sorry to see you go.",
                 ]);

        // Verify user status is updated
        $user->refresh();
        $this->assertEquals('deleted', $user->status);
    }

    /**
     * Test account deletion fails with incorrect password
     */
    public function test_account_deletion_fails_with_incorrect_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('CorrectPassword123!'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/v1/auth/delete-account', [
            'password' => 'WrongPassword123!',
            'confirmation' => 'DELETE_MY_ACCOUNT',
            'reason' => 'Testing account deletion',
        ]);

        $response->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Password is incorrect',
                 ]);
    }

    /**
     * Test registration creates referral code
     */
    public function test_registration_creates_referral_code()
    {
        $userData = [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email' => $this->faker->unique()->safeEmail,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'date_of_birth' => $this->faker->date('Y-m-d', '2000-01-01'),
            'gender' => 'male',
            'terms_accepted' => true,
            'privacy_accepted' => true,
        ];

        $response = $this->postJson('/api/v1/auth/register', $userData);

        $response->assertStatus(201);

        $user = User::where('email', $userData['email'])->first();
        $this->assertNotNull($user->referral_code);
        $this->assertEquals(8, strlen($user->referral_code));
    }

    /**
     * Test registration with referral code
     */
    public function test_registration_with_referral_code()
    {
        $referrer = User::factory()->create([
            'referral_code' => 'ABCD1234',
        ]);

        $userData = [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email' => $this->faker->unique()->safeEmail,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'date_of_birth' => $this->faker->date('Y-m-d', '2000-01-01'),
            'gender' => 'male',
            'terms_accepted' => true,
            'privacy_accepted' => true,
            'referral_code' => 'ABCD1234',
        ];

        $response = $this->postJson('/api/v1/auth/register', $userData);

        $response->assertStatus(201);

        $user = User::where('email', $userData['email'])->first();
        $this->assertEquals($referrer->id, $user->referred_by);
    }

    /**
     * Test social login creates or finds user
     */
    public function test_social_login_creates_or_finds_user()
    {
        $socialData = [
            'provider' => 'google',
            'provider_id' => '123456789',
            'name' => 'John Doe',
            'email' => $this->faker->unique()->safeEmail,
            'avatar' => 'https://example.com/avatar.jpg',
            'social_id' => '123456789',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];

        $response = $this->postJson('/api/v1/auth/social-login', $socialData);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'user',
                         'token',
                         'is_new_user',
                     ]
                 ]);

        $this->assertDatabaseHas('users', [
            'email' => $socialData['email'],
            'social_id' => $socialData['provider_id'],
            'registration_method' => $socialData['provider'],
        ]);
    }
} 