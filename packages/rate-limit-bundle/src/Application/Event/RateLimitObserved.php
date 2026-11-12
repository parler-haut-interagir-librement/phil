<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Application\Event;

use Phil\RateLimitBundle\Domain\ValueObject\Decision;

/**
 * Dispatched when a limiter in observation would have refused a request, which goes on: to measure a limit before it
 * is enforced, or to log, count or alert on what it would have stopped.
 *
 * It carries no address and no identifier in clear: the key is the hash the limiter counted under.
 */
final readonly class RateLimitObserved
{
    public function __construct(
        /** The name of the limiter that would have refused. */
        public string $limiter,
        /** The key the request counted under. */
        public string $key,
        /** What the limiter answered: a refusal the bundle did not enforce. */
        public Decision $decision,
        /** The name of the request's route, when it has one. */
        public ?string $route = null,
        /** The name of the form whose submission was counted; null for a controller or a route. */
        public ?string $form = null,
    ) {
    }
}
