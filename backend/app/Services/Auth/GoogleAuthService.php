<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthService
{
    /**
     * Generate the Google OAuth authorization redirect URL.
     */
    public function getAuthorizationUrl(): string
    {
        if (class_exists(Socialite::class)) {
            return Socialite::driver('google')
                ->stateless()
                ->scopes(['openid', 'profile', 'email'])
                ->redirect()
                ->getTargetUrl();
        }

        $clientId = Config::get('services.google.client_id');
        $redirectUri = Config::get('services.google.redirect');

        $params = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'access_type' => 'offline',
            'prompt' => 'select_account',
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
    }

    /**
     * Retrieve user profile from Google using authorization code.
     *
     * @return array{id: string, name: string, email: string, avatar: ?string}
     */
    public function getGoogleUserFromCode(string $code): array
    {
        if (class_exists(Socialite::class)) {
            try {
                $socialiteUser = Socialite::driver('google')->stateless()->user();

                return [
                    'id' => (string) $socialiteUser->getId(),
                    'name' => $socialiteUser->getName() ?? explode('@', $socialiteUser->getEmail())[0],
                    'email' => strtolower($socialiteUser->getEmail()),
                    'avatar' => $socialiteUser->getAvatar(),
                ];
            } catch (\Throwable $e) {
                // Fall back to direct HTTP exchange if Socialite driver encounters session/proxy issues
            }
        }

        $clientId = Config::get('services.google.client_id');
        $clientSecret = Config::get('services.google.client_secret');
        $redirectUri = Config::get('services.google.redirect');

        $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        if (! $tokenResponse->successful()) {
            throw ValidationException::withMessages([
                'code' => ['Failed to exchange authorization code with Google.'],
            ]);
        }

        $accessToken = $tokenResponse->json('access_token');

        $userInfoResponse = Http::withToken($accessToken)
            ->get('https://www.googleapis.com/oauth2/v3/userinfo');

        if (! $userInfoResponse->successful()) {
            throw ValidationException::withMessages([
                'code' => ['Failed to fetch user profile from Google.'],
            ]);
        }

        $data = $userInfoResponse->json();

        return [
            'id' => (string) ($data['sub'] ?? ''),
            'name' => $data['name'] ?? explode('@', $data['email'] ?? '')[0],
            'email' => strtolower($data['email'] ?? ''),
            'avatar' => $data['picture'] ?? null,
        ];
    }

    /**
     * Find or create user with domain parity and account linking.
     *
     * @param array{id: string, name: string, email: string, avatar: ?string} $googleData
     */
    public function findOrCreateUser(array $googleData): User
    {
        $googleId = $googleData['id'];
        $email = strtolower($googleData['email']);
        $name = $googleData['name'] ?: 'Google User';
        $avatar = $googleData['avatar'] ?? null;

        // 1. Check if user already linked with this Google ID
        $user = User::where('google_id', $googleId)->first();

        if ($user) {
            // Update profile info if changed
            $updates = [];
            if ($avatar && $user->avatar !== $avatar) {
                $updates['avatar'] = $avatar;
            }
            if ($name && $user->name !== $name && empty($user->name)) {
                $updates['name'] = $name;
            }
            if (! empty($updates)) {
                $user->update($updates);
            }

            return $user;
        }

        // 2. Account Linking: Check if user exists with matching email
        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            $existingUser->update([
                'google_id' => $googleId,
                'avatar' => $existingUser->avatar ?: $avatar,
                'email_verified_at' => $existingUser->email_verified_at ?: now(),
            ]);

            return $existingUser;
        }

        // 3. New User Registration Parity
        $newUser = User::create([
            'name' => $name,
            'email' => $email,
            'google_id' => $googleId,
            'avatar' => $avatar,
            'base_currency' => 'MYR',
            'email_verified_at' => now(),
            'password' => null,
        ]);

        event(new Registered($newUser));

        return $newUser;
    }
}
