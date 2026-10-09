<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_successfully(): void
    {
        $payload = [
            'name' => 'Ezra Lai',
            'email' => 'ezra@nextomni.com',
            'password' => 'SecurePass123!',
            'base_currency' => 'myr',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'base_currency',
                    'created_at',
                    'updated_at',
                ],
                'token',
                'token_type',
            ])
            ->assertJson([
                'user' => [
                    'name' => 'Ezra Lai',
                    'email' => 'ezra@nextomni.com',
                    'base_currency' => 'MYR',
                ],
                'token_type' => 'Bearer',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'ezra@nextomni.com',
            'base_currency' => 'MYR',
        ]);

        $this->assertMatchesRegularExpression('/^[0-9a-fA-F-]{36}$/', $response->json('user.id'));
    }

    public function test_registration_requires_unique_email(): void
    {
        User::factory()->create([
            'email' => 'existing@nextomni.com',
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Duplicate User',
            'email' => 'existing@nextomni.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_requires_minimum_password_length(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Short Pass',
            'email' => 'short@nextomni.com',
            'password' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'investor@nextomni.com',
            'password' => 'ValidPassword123!',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'investor@nextomni.com',
            'password' => 'ValidPassword123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'base_currency'],
                'token',
                'token_type',
            ])
            ->assertJson([
                'user' => [
                    'email' => 'investor@nextomni.com',
                ],
            ]);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'user@nextomni.com',
            'password' => 'CorrectPassword123!',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'user@nextomni.com',
            'password' => 'WrongPassword999!',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'The provided credentials do not match our records.',
            ]);
    }

    public function test_login_is_rate_limited_after_multiple_failures(): void
    {
        RateLimiter::clear('auth:login:rate@nextomni.com|127.0.0.1');

        User::factory()->create([
            'email' => 'rate@nextomni.com',
            'password' => 'CorrectPassword!',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'rate@nextomni.com',
                'password' => 'BadPassword',
            ])->assertStatus(422);
        }

        // 6th attempt should be blocked by RateLimiter
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'rate@nextomni.com',
            'password' => 'BadPassword',
        ]);

        $response->assertStatus(429);
    }

    public function test_authenticated_user_can_retrieve_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Profile User',
            'email' => 'profile@nextomni.com',
            'base_currency' => 'SGD',
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'user' => [
                    'name' => 'Profile User',
                    'email' => 'profile@nextomni.com',
                    'base_currency' => 'SGD',
                ],
            ]);
    }

    public function test_authenticated_user_can_update_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'base_currency' => 'MYR',
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/auth/profile', [
                'name' => 'Updated Name',
                'base_currency' => 'usd',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'user' => [
                    'name' => 'Updated Name',
                    'base_currency' => 'USD',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'base_currency' => 'USD',
        ]);
    }

    public function test_authenticated_user_can_logout_and_revoke_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('logout_test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Successfully logged out.',
            ]);

        // Flush resolved guard instance in test container, then verify revoked token is rejected
        $this->app['auth']->forgetGuards();

        $subsequent = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $subsequent->assertStatus(401);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }
}
