<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Benchmark;

use Phil\BrowserSecurityBundle\Application\ProtectionOverride;
use Phil\BrowserSecurityBundle\Application\SecurityHeaders;

/**
 * The neighbouring headers of one response, paid by every page: without override, the usual case, and with one.
 */
final readonly class SecurityHeadersBench
{
    private SecurityHeaders $headers;

    private ProtectionOverride $override;

    public function __construct()
    {
        $this->headers = new SecurityHeaders([
            'Permissions-Policy' => ['camera' => [], 'microphone' => [], 'geolocation' => ['self']],
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'same-site',
            'X-Permitted-Cross-Domain-Policies' => 'none',
            'Origin-Agent-Cluster' => '?1',
            'Strict-Transport-Security' => 'max-age=31536000',
        ], ['/embed' => null, '/admin' => 'DENY']);
        $this->override = new ProtectionOverride(add: ['Permissions-Policy' => ['camera' => ['self']]], replace: ['X-Frame-Options' => 'DENY']);
    }

    public function benchWithoutOverride(): void
    {
        $this->headers->resolve('/page/one');
    }

    public function benchWithAnOverride(): void
    {
        $this->headers->resolve('/page/one', [$this->override]);
    }
}
