<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Infrastructure\BrowserSecurity;

use Override;
use Phil\BrowserSecurityBundle\Application\BrowserSecurityContext;
use Phil\BrowserSecurityBundle\Application\ProtectionOverride;
use Phil\EmbedBundle\Application\Contract\FramingPolicy;
use Phil\EmbedBundle\Domain\ValueObject\Origin;
use Symfony\Component\HttpFoundation\Request;

/**
 * The framing through phil/browser-security-bundle: an override of the response's protections replaces the sources of
 * `frame-ancestors` in its content security policy, and drops `X-Frame-Options` when the origin is allowed.
 */
final readonly class BrowserSecurityFraming implements FramingPolicy
{
    public function __construct(
        private BrowserSecurityContext $context,
    ) {
    }

    #[Override]
    public function allow(Request $request, Origin $origin): void
    {
        $this->context->override(new ProtectionOverride(
            replace: ['Content-Security-Policy' => ['frame-ancestors' => [$origin->toString()]]],
            remove: ['X-Frame-Options'],
        ));
    }

    #[Override]
    public function deny(Request $request): void
    {
        $this->context->override(new ProtectionOverride(replace: ['Content-Security-Policy' => ['frame-ancestors' => ["'none'"]]]));
    }
}
