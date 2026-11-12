<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Infrastructure\Security;

use Phil\IdentityClientBundle\Application\Exception\SessionLifetimeExceededException;
use Psr\Clock\ClockInterface;
use Symfony\Component\Security\Http\Event\CheckRefreshedUserEvent;

/**
 * Deauthenticates a session that reached the cap. It is what ends a session whose logout token never arrived.
 */
final readonly class SessionCapListener
{
    public function __construct(
        private ClockInterface $clock,
        private int $maxLifetime,
    ) {
    }

    public function __invoke(CheckRefreshedUserEvent $event): void
    {
        $startedAt = TokenAttribute::integer($event->getToken(), TokenAttribute::SESSION_STARTED_AT);
        if (null !== $startedAt && $this->clock->now()->getTimestamp() - $startedAt >= $this->maxLifetime) {
            $event->setUserChanged(true, new SessionLifetimeExceededException());
        }
    }
}
