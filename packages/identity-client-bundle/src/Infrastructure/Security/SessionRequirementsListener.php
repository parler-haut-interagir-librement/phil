<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Infrastructure\Security;

use Phil\IdentityClientBundle\Application\Contract\ReplayFloor;
use Phil\IdentityClientBundle\Application\Elevation\AssuranceLevels;
use Phil\IdentityClientBundle\Application\Exception\AssuranceLevelNotReachedException;
use Phil\IdentityClientBundle\Application\Exception\SessionPredatesReplayFloorException;
use Symfony\Component\Security\Http\Event\CheckRefreshedUserEvent;

/**
 * Deauthenticates a session the configuration no longer admits: one below the required assurance level, which a
 * raised requirement leaves behind, or one that began before the replay floor, whose logout may have been forgotten.
 *
 * Only the sessions this bundle signed in are judged; a token of another authenticator is left alone.
 */
final readonly class SessionRequirementsListener
{
    public function __construct(
        private AssuranceLevels $levels,
        private ?ReplayFloor $floor,
    ) {
    }

    public function __invoke(CheckRefreshedUserEvent $event): void
    {
        $token = $event->getToken();
        if (null === TokenAttribute::integer($token, TokenAttribute::SESSION_STARTED_AT)) {
            return;
        }

        if (!$this->levels->reachesRequired(TokenAttribute::string($token, TokenAttribute::ASSURANCE))) {
            $event->setUserChanged(true, new AssuranceLevelNotReachedException());

            return;
        }

        if (!$this->floor instanceof ReplayFloor) {
            return;
        }

        $signedInAt = TokenAttribute::integer($token, TokenAttribute::SIGNED_IN_AT);
        if (null === $signedInAt || $signedInAt < $this->floor->floor()) {
            $event->setUserChanged(true, new SessionPredatesReplayFloorException());
        }
    }
}
