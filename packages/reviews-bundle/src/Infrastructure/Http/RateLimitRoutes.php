<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Infrastructure\Http;

use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Gives the forms of the reviews the rules of phil/rate-limit-bundle the configuration names, as the request attribute
 * `_rate_limit` its listener reads, once the router has matched the route. A route whose own `_rate_limit` the
 * application set keeps it.
 */
final readonly class RateLimitRoutes
{
    public const string ATTRIBUTE = '_rate_limit';

    /**
     * @param list<mixed> $write the rules of the review form
     * @param list<mixed> $vote  the rules of the votes, responses and contests
     */
    public function __construct(
        private array $write,
        private array $vote,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || $request->attributes->has(self::ATTRIBUTE)) {
            return;
        }

        $rules = match ($request->attributes->get('_route')) {
            ReviewsController::WRITE_ROUTE => $this->write,
            ReviewsController::VOTE_ROUTE, ReviewsController::RESPOND_ROUTE, ReviewsController::CONTEST_ROUTE => $this->vote,
            default => [],
        };

        if ([] !== $rules) {
            $request->attributes->set(self::ATTRIBUTE, $rules);
        }
    }
}
