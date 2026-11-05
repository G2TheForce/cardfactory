<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser hardening headers for every response. Routes that set their own
 * Content-Security-Policy (e.g. standalone SVG previews) keep it.
 *
 * The CSP allows only this site plus Google Fonts and Google Maps embeds.
 * Alpine.js evaluates expressions, so scripts need 'unsafe-eval'; inline
 * event handlers (confirm dialogs) need 'unsafe-inline'. It is skipped
 * while the Vite dev server runs (hot reloading uses its own origin).
 */
class SecurityHeaders
{
    public function __construct(private readonly Vite $vite) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);
        $headers = $response->headers;

        $defaults = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];
        foreach ($defaults as $name => $value) {
            if (! $headers->has($name)) {
                $headers->set($name, $value);
            }
        }

        if (config('smartcard.security.csp') && ! $headers->has('Content-Security-Policy') && ! $this->vite->isRunningHot()) {
            $headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "font-src 'self' data: https://fonts.gstatic.com",
                "img-src 'self' data: blob: https:",
                "connect-src 'self'",
                'frame-src '.implode(' ', ["'self'", 'https://www.google.com', 'https://maps.google.com']),
                "frame-ancestors 'self'",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
            ]));
        }

        if ($request->isSecure() && config('smartcard.security.hsts') && ! $headers->has('Strict-Transport-Security')) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
