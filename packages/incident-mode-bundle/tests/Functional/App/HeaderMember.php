<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Tests\Functional\App;

use Override;
use Phil\IncidentModeBundle\Application\Contract\CurrentMember;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The member named by the `X-Member` header of the request; a visitor without it.
 */
final readonly class HeaderMember implements CurrentMember
{
    public function __construct(
        private RequestStack $requests,
    ) {
    }

    #[Override]
    public function identifier(): ?string
    {
        $member = $this->requests->getMainRequest()?->headers->get('X-Member');

        return null === $member || '' === $member ? null : $member;
    }
}
