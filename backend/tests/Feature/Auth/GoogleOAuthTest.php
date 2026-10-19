<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\GoogleAuthService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class GoogleOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'dummy-google-client-id.apps.googleusercontent.com',
            'services.google.client_secret' => 'dummy-google-client-secret',
            'services.google.redirect' => 'http://localhost:8000/api/v1/auth/google/callback',
        ]);
    }

    public function test_google_redirect_returns_valid_oauth_url(): void
    {
        $response = $this->getJson('/api/v1/auth/google/redirect?format=json');

        $response->assertStatus(200)
            ->assertJsonStructure(['url']);

        $url = $response->json('url');
        $this->assertStringContainsString('accounts.google.com', $url);
        $this->assertStringContainsString('client_id=', $url);
    }

    public function test_google_callback_registers_new_user_with_full_parity(): void
    {
        Event::fake([Registered::class]);

        $mockAuthService = Mockery::mock(GoogleAuthService::class);
        $mockAuthService->shouldReceive('getGoogleUserFromCode')
            ->once()
            ->with('valid-auth-code')
            ->andReturn([
                'id' => 'google-user-12345',
                'name' => 'Alex Rivera',
                'email' => 'alex.rivera@gmail.com',
                'avatar' => 'https://lh3.googleusercontent.com/avatar/alex.jpg',
            ]);

        // Use real findOrCreateUser logic by passing through
        $realService = new GoogleAuthService();
        $mockAuthService->shouldReceive('findOrCreateUser')
            ->once()
            ->andReturnUsing(function ($googleData) use ($realService) {
                return $realService->findOrCreateUser($googleData);
            });

        $this->app->instance(GoogleAuthService::class, $mockAuthService);

        $response = $this->postJson('/api/v1/auth/google/callback', [
            'code' => 'valid-auth-code',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'avatar',
                    'base_currency',
                    'has_password',
                    'created_at',
                    'updated_at',
                ],
                'token',
                'token_type',
            ])
            ->assertJson([
                'user' => [
                    'name' => 'Alex Rivera',
                    'email' => 'alex.rivera@gmail.com',
                    'avatar' => 'https://lh3.googleusercontent.com/avatar/alex.jpg',
                    'base_currency' => 'MYR',
                    'has_password' => false,
                ],
                'token_type' => 'Bearer',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'alex.rivera@gmail.com',
            'google_id' => 'google-user-12345',
            'avatar' => 'https://lh3.googleusercontent.com/avatar/alex.jpg',
            'base_currency' => 'MYR',
        ]);

        Event::assertDispatched(Registered::class);
    }

    public function test_google_callback_authenticates_existing_google_user(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'existing.google@gmail.com',
            'google_id' => 'google-user-existing-99',
            'avatar' => 'https://lh3.googleusercontent.com/old-avatar.jpg',
            'password' => null,
        ]);

        $mockAuthService = Mockery::mock(GoogleAuthService::class);
        $mockAuthService->shouldReceive('getGoogleUserFromCode')
            ->once()
            ->with('valid-auth-code')
            ->andReturn([
                'id' => 'google-user-existing-99',
                'name' => 'Existing User',
                'email' => 'existing.google@gmail.com',
                'avatar' => 'https://lh3.googleusercontent.com/new-avatar.jpg',
            ]);

        $realService = new GoogleAuthService();
        $mockAuthService->shouldReceive('findOrCreateUser')
            ->once()
            ->andReturnUsing(function ($googleData) use ($realService) {
                return $realService->findOrCreateUser($googleData);
            });

        $this->app->instance(GoogleAuthService::class, $mockAuthService);

        $response = $this->postJson('/api/v1/auth/google/callback', [
            'code' => 'valid-auth-code',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'user' => [
                    'id' => $existingUser->id,
                    'email' => 'existing.google@gmail.com',
                    'avatar' => 'https://lh3.googleusercontent.com/new-avatar.jpg',
                ],
            ]);

        $this->assertEquals(1, User::where('email', 'existing.google@gmail.com')->count());
        $this->assertDatabaseHas('users', [
            'id' => $existingUser->id,
            'avatar' => 'https://lh3.googleusercontent.com/new-avatar.jpg',
        ]);
    }

    public function test_google_callback_links_existing_email_account_safely(): void
    {
        // User registered previously via password
        $passwordUser = User::factory()->create([
            'email' => 'investor@gmail.com',
            'password' => 'SecurePass123!',
            'google_id' => null,
            'avatar' => null,
        ]);

        $mockAuthService = Mockery::mock(GoogleAuthService::class);
        $mockAuthService->shouldReceive('getGoogleUserFromCode')
            ->once()
            ->with('valid-auth-code')
            ->andReturn([
                'id' => 'google-linked-id-555',
                'name' => 'Investor Name',
                'email' => 'investor@gmail.com',
                'avatar' => 'https://lh3.googleusercontent.com/avatar/investor.png',
            ]);

        $realService = new GoogleAuthService();
        $mockAuthService->shouldReceive('findOrCreateUser')
            ->once()
            ->andReturnUsing(function ($googleData) use ($realService) {
                return $realService->findOrCreateUser($googleData);
            });

        $this->app->instance(GoogleAuthService::class, $mockAuthService);

        $response = $this->postJson('/api/v1/auth/google/callback', [
            'code' => 'valid-auth-code',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'user' => [
                    'id' => $passwordUser->id,
                    'email' => 'investor@gmail.com',
                    'avatar' => 'https://lh3.googleusercontent.com/avatar/investor.png',
                    'has_password' => true,
                ],
            ]);

        // Ensure user count did not duplicate
        $this->assertEquals(1, User::where('email', 'investor@gmail.com')->count());

        // Ensure google_id is linked to the existing user
        $this->assertDatabaseHas('users', [
            'id' => $passwordUser->id,
            'google_id' => 'google-linked-id-555',
            'avatar' => 'https://lh3.googleusercontent.com/avatar/investor.png',
        ]);
    }

    public function test_google_callback_rejects_missing_code(): void
    {
        $response = $this->postJson('/api/v1/auth/google/callback', []);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Authorization code is missing.',
            ]);
    }

    public function test_google_callback_handles_access_denied_gracefully(): void
    {
        $response = $this->get('/api/v1/auth/google/callback?error=access_denied');

        $response->assertStatus(302);
        $this->assertStringContainsString('auth_error=access_denied', $response->headers->get('Location'));
    }

    public function test_google_callback_browser_redirect_uses_ephemeral_ticket_without_exposing_token(): void
    {
        $mockAuthService = Mockery::mock(GoogleAuthService::class);
        $mockAuthService->shouldReceive('getGoogleUserFromCode')
            ->once()
            ->with('browser-code-123')
            ->andReturn([
                'id' => 'google-user-browser-1',
                'name' => 'Browser User',
                'email' => 'browser.user@gmail.com',
                'avatar' => null,
            ]);

        $realService = new GoogleAuthService();
        $mockAuthService->shouldReceive('findOrCreateUser')
            ->once()
            ->andReturnUsing(function ($googleData) use ($realService) {
                return $realService->findOrCreateUser($googleData);
            });

        $this->app->instance(GoogleAuthService::class, $mockAuthService);

        $response = $this->get('/api/v1/auth/google/callback?code=browser-code-123');

        $response->assertStatus(302);
        $location = $response->headers->get('Location');

        // Verify ticket parameter is present and token is NEVER in the query string
        $this->assertStringContainsString('ticket=', $location);
        $this->assertStringNotContainsString('auth_token=', $location);

        // Extract ticket
        parse_str(parse_url($location, PHP_URL_QUERY), $params);
        $this->assertNotEmpty($params['ticket']);
        $ticket = $params['ticket'];

        // Now test exchange endpoint with this ticket
        $exchangeResponse = $this->postJson('/api/v1/auth/google/exchange', [
            'ticket' => $ticket,
        ]);

        $exchangeResponse->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'email', 'name'],
                'token',
                'token_type',
            ])
            ->assertJson([
                'user' => ['email' => 'browser.user@gmail.com'],
                'token_type' => 'Bearer',
            ]);

        // Attempting to reuse the same ticket immediately fails (replay attack protection)
        $replayResponse = $this->postJson('/api/v1/auth/google/exchange', [
            'ticket' => $ticket,
        ]);

        $replayResponse->assertStatus(422)
            ->assertJson([
                'message' => 'The authentication ticket is invalid or has expired.',
            ]);
    }

    public function test_exchange_ticket_rejects_invalid_or_missing_ticket(): void
    {
        $response = $this->postJson('/api/v1/auth/google/exchange', [
            'ticket' => 'non-existent-or-expired-ticket-abc',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'The authentication ticket is invalid or has expired.',
            ]);

        $missingResponse = $this->postJson('/api/v1/auth/google/exchange', []);
        $missingResponse->assertStatus(422);
    }
}
