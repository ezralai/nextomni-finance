<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\Auth\GoogleAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class GoogleAuthController extends Controller
{
    public function __construct(
        protected GoogleAuthService $googleAuthService
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

            // Browser redirect to frontend with authentication token
            return redirect()->away("{$frontendUrl}/?auth_token={$token}");
        } catch (\Throwable $e) {
            Log::error('Google OAuth callback failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Failed to authenticate with Google.',
                    'error' => $e->getMessage(),
                ], 422);
            }

            return redirect()->away("{$frontendUrl}/?auth_error=auth_failed");
        }
    }
}
