<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Auth\GoogleAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
    public function __construct(
        protected GoogleAuthService $googleAuthService,
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Redirect the user to the Google OAuth consent screen.
     */
    public function redirect(Request $request): JsonResponse|RedirectResponse
    {
        $url = $this->googleAuthService->getAuthorizationUrl();

        if ($request->wantsJson() || $request->query('format') === 'json') {
            return response()->json([
                'url' => $url,
            ]);
        }

        return redirect()->away($url);
    }

    /**
     * Handle the callback from Google OAuth.
     */
    public function callback(Request $request): JsonResponse|RedirectResponse
    {
        $frontendUrl = rtrim(Config::get('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');

        // Check for Google OAuth cancellation or error
        if ($request->has('error')) {
            $error = $request->query('error');
            Log::warning('Google OAuth callback returned error', ['error' => $error]);

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Google authorization was denied or cancelled.',
                    'error' => $error,
                ], 400);
            }

            return redirect()->away("{$frontendUrl}/?auth_error=" . urlencode($error));
        }

        $code = $request->input('code');

        if (! $code) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Authorization code is missing.',
                ], 422);
            }

            return redirect()->away("{$frontendUrl}/?auth_error=missing_code");
        }

        try {
            $googleData = $this->googleAuthService->getGoogleUserFromCode($code);
            $user = $this->googleAuthService->findOrCreateUser($googleData);

            $this->auditLogService->log(
                $user,
                $user->wasRecentlyCreated ? 'user.oauth_registered' : 'user.oauth_login',
                $user,
                null,
                [
                    'id' => $user->id,
                    'email' => $user->email,
                    'provider' => 'google',
                ]
            );

            $token = $user->createToken('auth_token')->plainTextToken;

            // If JSON requested (e.g. SPA direct exchange)
            if ($request->wantsJson() || $request->isMethod('post')) {
                return response()->json([
                    'message' => 'Authenticated successfully.',
                    'user' => new UserResource($user),
                    'token' => $token,
                    'token_type' => 'Bearer',
                ]);
            }

            // Issue an ephemeral, short-lived single-use ticket to prevent token exposure in GET query string
            $ticket = Str::random(64);
            Cache::put("oauth_ticket:{$ticket}", [
                'user_id' => $user->id,
                'token' => $token,
            ], now()->addMinutes(2));

            // Browser redirect to frontend with ephemeral ticket
            return redirect()->away("{$frontendUrl}/?ticket={$ticket}");
        } catch (\Throwable $e) {
            Log::error('Google OAuth callback failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Failed to authenticate with Google.',
                ], 422);
            }

            return redirect()->away("{$frontendUrl}/?auth_error=auth_failed");
        }
    }

    /**
     * Atomically exchange an ephemeral OAuth ticket for the access token and user profile.
     */
    public function exchangeTicket(Request $request): JsonResponse
    {
        $ticket = $request->input('ticket');

        if (! $ticket || ! is_string($ticket)) {
            return response()->json([
                'message' => 'The ticket field is required.',
            ], 422);
        }

        // Atomically pull and invalidate ticket (prevent replay attacks)
        $cached = Cache::pull("oauth_ticket:{$ticket}");

        if (! $cached || ! isset($cached['user_id'], $cached['token'])) {
            return response()->json([
                'message' => 'The authentication ticket is invalid or has expired.',
            ], 422);
        }

        $user = User::find($cached['user_id']);

        if (! $user) {
            return response()->json([
                'message' => 'User associated with ticket no longer exists.',
            ], 404);
        }

        return response()->json([
            'message' => 'Authenticated successfully.',
            'user' => new UserResource($user),
            'token' => $cached['token'],
            'token_type' => 'Bearer',
        ]);
    }
}
