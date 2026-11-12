<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Application\Event;

use Phil\RateLimitBundle\Domain\ValueObject\Decision;

/**
 * Dispatched when a limiter refuses a request, before the refusal is answered: to log it, count it or alert on it.
 *
 * It carries no address and no identifier in clear: the key is the hash the limiter counted under.
 */
final readonly class RateLimitExceeded
{
    public function __construct(
        /** The name of the limiter that refused. */
        public string $limiter,
        /** The key the request counted under. */
        public string $key,
        /** What the limiter answered. */
        public Decision $decision,
        /** The name of the request's route, when it has one. */
        public ?string $route = null,
        /** The name of the form whose submission was limited; null for a controller or a route. */
        public ?string $form = null,
    ) {
    }
}
