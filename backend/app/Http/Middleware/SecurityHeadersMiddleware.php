<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Apply OWASP recommended defense-in-depth security headers.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Prevent clickjacking / framing
        $response->headers->set('X-Frame-Options', 'DENY');

        // Referrer policy: send origin only on cross-origin requests, full URL on same-origin
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Restrict sensitive browser features
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // Prevent cross-site scripting filter legacy bypasses
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        return $response;
    }
}
