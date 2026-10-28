<?php

it('sends baseline security headers', function () {
    $this->get(route('portal.login'))
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy');
});

it('sends a nonce-based CSP in report-only mode by default, and stamps the nonce on inline scripts', function () {
    $response = $this->get(route('portal.login'));

    $csp = $response->headers->get('Content-Security-Policy-Report-Only');
    preg_match("/'nonce-([^']+)'/", (string) $csp, $m);

    expect($csp)->toContain("default-src 'self'")
        ->toContain("object-src 'none'")
        ->toContain("frame-ancestors 'self'")
        ->not->toContain("script-src 'self' 'unsafe-inline'")
        ->and($response->headers->has('Content-Security-Policy'))->toBeFalse()
        ->and($m[1] ?? null)->not->toBeNull()
        ->and($response->getContent())->toContain('nonce="'.$m[1].'"'); // Ziggy's @routes block
});

it('enforces the CSP when configured', function () {
    config(['security.csp.enforce' => true]);

    $this->get(route('portal.login'))->assertHeader('Content-Security-Policy');
});

it('does not send a CSP on JSON API responses', function () {
    $response = $this->getJson(route('api.v1.branding.show'));

    expect($response->headers->has('Content-Security-Policy-Report-Only'))->toBeFalse()
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});
