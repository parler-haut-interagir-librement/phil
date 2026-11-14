<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Functional\App;

use Override;
use Phil\NotificationsBundle\Application\Contract\CurrentRecipient;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The recipient the request names in its `X-User` header, for the tests.
 */
final readonly class HeaderRecipient implements CurrentRecipient
{
    public function __construct(
        private RequestStack $requests,
    ) {
    }

    #[Override]
    public function id(): ?string
    {
        return $this->requests->getCurrentRequest()?->headers->get('X-User');
    }
}
