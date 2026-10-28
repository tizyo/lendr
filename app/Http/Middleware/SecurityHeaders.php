<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser security headers (scope 14.2: "XSS prevention: … Content-
 * Security-Policy header").
 *
 * The CSP is nonce-based: Vite tags, Ziggy's @routes block and the PWA's
 * service-worker snippet carry the per-request nonce, so no inline script
 * needs 'unsafe-inline'. See config/security.php for Report-Only vs enforce.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Must be generated before the view renders so @vite / @routes can stamp it.
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff', false);
        $headers->set('X-Frame-Options', 'SAMEORIGIN', false);
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        // Camera: KYC selfie / document capture. Geolocation: field-collection check-ins.
        $headers->set('Permissions-Policy', 'camera=(self), geolocation=(self), microphone=(), payment=(), usb=()', false);

        if ($request->isSecure() && ! app()->isLocal()) {
            $headers->set('Strict-Transport-Security', 'max-age='.config('security.hsts_max_age').'; includeSubDomains', false);
        }

        if ($this->wantsCsp($request, $response)) {
            $name = config('security.csp.enforce') ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only';
            $headers->set($name, $this->policy($nonce));
        }

        return $response;
    }

    private function wantsCsp(Request $request, Response $response): bool
    {
        return config('security.csp.enabled')
            && ! app()->isLocal()
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            // Scribe's generated API docs pull scripts from public CDNs.
            && ! str_starts_with((string) $request->route()?->getName(), 'scribe');
    }

    private function policy(string $nonce): string
    {
        $extraScripts = trim((string) config('security.csp.extra_script_src'));
        $extraConnect = trim((string) config('security.csp.extra_connect_src'));

        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' {$extraScripts}",
            // Vue/Inertia set inline style attributes; Bunny Fonts serves the Inter stylesheet.
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "font-src 'self' https://fonts.bunny.net data:",
            // Logos and documents can come from object storage; QR codes are data: URIs.
            "img-src 'self' data: blob: https:",
            "connect-src 'self' {$this->websocketOrigin()} {$extraConnect}",
            "worker-src 'self'",
            "manifest-src 'self'",
            "frame-src 'self'",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ];

        if ($report = config('security.csp.report_uri')) {
            $directives[] = "report-uri {$report}";
        }

        return implode('; ', array_map(fn ($d) => preg_replace('/\s+/', ' ', trim($d)), $directives));
    }

    /** Laravel Reverb (real-time notifications) when it runs on its own host/port. */
    private function websocketOrigin(): string
    {
        $options = config('broadcasting.connections.reverb.options', []);
        $host = $options['host'] ?? null;

        if (! $host) {
            return '';
        }

        $scheme = ($options['scheme'] ?? 'https') === 'https' ? 'wss' : 'ws';
        $port = $options['port'] ?? null;

        return "{$scheme}://{$host}".($port && ! in_array((int) $port, [80, 443], true) ? ":{$port}" : '');
    }
}
