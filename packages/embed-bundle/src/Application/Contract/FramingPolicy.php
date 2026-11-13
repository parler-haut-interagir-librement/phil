<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Application\Contract;

use Phil\EmbedBundle\Domain\ValueObject\Origin;
use Symfony\Component\HttpFoundation\Request;

/**
 * Who may frame the response to a request: the bundle's adapters go through phil/browser-security-bundle when it is
 * installed, or write the `Content-Security-Policy` header themselves.
 *
 * Invariants: an allowed response may be framed by that origin alone — `frame-ancestors` names it, and
 * `X-Frame-Options` is dropped —; a denied one by none.
 */
interface FramingPolicy
{
    public function allow(Request $request, Origin $origin): void;

    public function deny(Request $request): void;
}
