<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Infrastructure\Http;

use Phil\RateLimitBundle\Application\Exception\TooManyRequests;
use Phil\RateLimitBundle\Application\RateLimitEnforcer;
use Phil\RateLimitBundle\Application\RateLimitRules;
use Phil\RateLimitBundle\Domain\ValueObject\Decision;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Holds the main request to its route default `_rate_limit` before the controller runs, and writes the rate-limit
 * headers of the tightest limiter — of the route default and of Symfony's `#[RateLimit]` alike — on the response. A
 * `429` keeps the headers of the limiter that refused, which the responder wrote.
 *
 * It runs on `kernel.controller` before Symfony's attribute listener, which keeps what its attributes consumed under the
 * same request attribute: the rules are read, then the attribute is removed, on every request. A refusal throws
 * `TooManyRequests`, which the responder answers. A sub-request is never counted: its main request was.
 *
 * The decision travels in a request attribute, so that the service keeps nothing between requests.
 */
final readonly class RateLimitListener
{
    /** The request attribute that keeps the decision the response headers are written from. */
    public const string DECISION_ATTRIBUTE = RateLimitEnforcer::DECISION_ATTRIBUTE;

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
        $request = $event->getRequest();
        $value = $request->attributes->get(self::ROUTE_DEFAULT);
        $request->attributes->remove(self::ROUTE_DEFAULT);

        if (!$event->isMainRequest()) {
            return;
        }

        $decision = $this->enforcer->enforce($request, RateLimitRules::from($value));

        if ($decision instanceof Decision) {
            $this->enforcer->remember($request, $decision);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $decision = $event->getRequest()->attributes->get(self::DECISION_ATTRIBUTE);

        if ($event->isMainRequest() && $decision instanceof Decision && Response::HTTP_TOO_MANY_REQUESTS !== $event->getResponse()->getStatusCode()) {
            $event->getResponse()->headers->add($this->enforcer->headersOf($decision));
        }
    }
}
