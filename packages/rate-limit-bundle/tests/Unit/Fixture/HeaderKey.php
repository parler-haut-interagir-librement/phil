<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit\Fixture;

use Override;
use Phil\RateLimitBundle\Application\Contract\KeyResolver;
use Symfony\Component\HttpFoundation\Request;

/**
 * The key a request names in a header, `X-Account` by default, or none.
 */
final readonly class HeaderKey implements KeyResolver
{
    public function __construct(
        private string $header = 'X-Account',
    ) {
    }

    #[Override]
    public function keyOf(Request $request): ?string
    {
        return $request->headers->get($this->header);
    }
}
