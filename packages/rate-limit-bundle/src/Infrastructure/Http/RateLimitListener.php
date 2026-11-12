<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Infrastructure\Http;

use Phil\RateLimitBundle\Application\Exception\TooManyRequests;
use Phil\RateLimitBundle\Application\RateLimitEnforcer;
use Phil\RateLimitBundle\Application\RateLimitRules;
use Phil\RateLimitBundle\Domain\Attribute\RateLimit;
use Phil\RateLimitBundle\Domain\ValueObject\Decision;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Holds the main request to the `#[RateLimit]` attributes of its controller and to its route default `_rate_limit`,
 * before the controller runs, and writes the rate-limit headers of the tightest limiter on the response.
 *
 * The attributes of the class come first, then those of the action, then the route's rules. A refusal throws
 * `TooManyRequests`, which the responder answers. A sub-request is never counted: its main request was.
 *
 * The decision travels in a request attribute, so that the service keeps nothing between requests.
 */
final readonly class RateLimitListener
{
    /** The request attribute that keeps the decision the response headers are written from. */
    public const string DECISION_ATTRIBUTE = '_phil_rate_limit';

    /** The route default that holds the route's rules. */
    public const string ROUTE_DEFAULT = '_rate_limit';

    public function __construct(
        private RateLimitEnforcer $enforcer,
    ) {
    }

    /**
     * @throws TooManyRequests when a limiter refuses the request
     */
    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $rules = [...$event->getAttributes(RateLimit::class), ...RateLimitRules::from($request->attributes->get(self::ROUTE_DEFAULT))];
        $decision = $this->enforcer->enforce($request, $rules);

        if ($decision instanceof Decision) {
            $request->attributes->set(self::DECISION_ATTRIBUTE, $decision);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $decision = $event->getRequest()->attributes->get(self::DECISION_ATTRIBUTE);

        if ($decision instanceof Decision) {
            $event->getResponse()->headers->add($this->enforcer->headersOf($decision));
        }
    }
}
