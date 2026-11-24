<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Tests\Functional\App;

use Override;
use Phil\ReviewsBundle\Application\Contract\CurrentReviewer;
use Phil\ReviewsBundle\Domain\ValueObject\MemberId;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The member the `X-User` header of the request names, as a firewall would sign them in.
 */
final readonly class HeaderReviewer implements CurrentReviewer
{
    public function __construct(
        private RequestStack $requests,
    ) {
    }

    #[Override]
    public function member(): ?MemberId
    {
        $name = $this->requests->getMainRequest()?->headers->get('X-User');

        return null === $name ? null : MemberId::fromString($name);
    }
}
