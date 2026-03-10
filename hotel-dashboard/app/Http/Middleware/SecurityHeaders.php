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

        // Content Security Policy (CSP)
        // This allows scripts from same origin and Vite's development server
        $csp = "default-src 'self'; ";
        $csp .= "script-src 'self' 'unsafe-inline' 'unsafe-eval'; "; // unsafe-inline & unsafe-eval needed for Vite/Vue
        $csp .= "style-src 'self' 'unsafe-inline' https://fonts.bunny.net; "; // unsafe-inline needed for Tailwind, fonts.bunny.net for external fonts
        $csp .= "img-src 'self' data: https:; ";
        $csp .= "font-src 'self' data: https://fonts.bunny.net; "; // Allow fonts from fonts.bunny.net
        $csp .= "connect-src 'self'; ";
        $csp .= "form-action 'self'; "; // Allow form submissions to same origin
        $csp .= "frame-ancestors 'none';";

        $response->headers->set('Content-Security-Policy', $csp);

        // Strict Transport Security (HSTS) - Only enable in production with HTTPS
        if (config('app.env') === 'production') {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
