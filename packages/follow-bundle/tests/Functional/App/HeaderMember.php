<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Tests\Functional\App;

use Override;
use Phil\NotificationsBundle\Application\Contract\CurrentRecipient;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The member the request names in its `X-Member` header, for the tests.
 */
final readonly class HeaderMember implements CurrentRecipient
{
    public function __construct(
        private RequestStack $requests,
    ) {
    }

    #[Override]
    public function id(): ?string
    {
        return $this->requests->getCurrentRequest()?->headers->get('X-Member');
    }
}
