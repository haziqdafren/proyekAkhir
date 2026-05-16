<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request and add security headers.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent clickjacking attacks
        $response->headers->set('X-Frame-Options', 'DENY');

        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Control referrer information
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Enable XSS protection (for older browsers)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Content Security Policy — skip entirely in local dev (Vite port changes dynamically).
        // 'unsafe-inline' is required for script-src because Inertia emits an inline <script>
        // tag to pass page props. Without it the app breaks (#10).
        if (!app()->environment('local', 'development')) {
            $csp = "default-src 'self'; ";
            $csp .= "script-src 'self' 'unsafe-inline'; ";
            $csp .= "style-src 'self' 'unsafe-inline' https://fonts.bunny.net; ";
            $csp .= "connect-src 'self'; ";
            $csp .= "img-src 'self' data: https:; ";
            $csp .= "font-src 'self' data: https://fonts.bunny.net; ";
            $csp .= "form-action 'self'; ";
            $csp .= "object-src 'none'; ";
            $csp .= "base-uri 'self'; ";
            $csp .= "frame-ancestors 'none';";
            $response->headers->set('Content-Security-Policy', $csp);
        }

        // Strict Transport Security (HSTS) - Only enable in production with HTTPS
        if (config('app.env') === 'production') {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
