<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Unit\Fixture;

use Override;
use Phil\TermsBundle\Application\Contract\MemberResolver;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The member given, or the one of the `X-Member` header of the current request.
 */
final readonly class FixedMember implements MemberResolver
{
    public function __construct(
        private ?string $member = null,
        private ?RequestStack $requests = null,
    ) {
    }

    #[Override]
    public function current(): ?string
    {
        return $this->requests?->getCurrentRequest()?->headers->get('X-Member') ?? $this->member;
    }
}
