<?php

declare(strict_types=1);

namespace Phil\OpenBadgesBundle\Infrastructure\Http;

use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Puts the checks of the verification page under a limiter of `phil/rate-limit-bundle`: on the main request posted to
 * the page, once the router has matched it, it gives the request the rule `_rate_limit` that bundle reads before the
 * controller runs, unless the route already holds one. Showing the page is never counted, only checking.
 *
 * It keeps nothing between requests.
 */
final readonly class VerificationRateLimit
{
    /** The request attribute, a route default otherwise, that `phil/rate-limit-bundle` reads its rules from. */
    public const string ATTRIBUTE = '_rate_limit';

    public function __construct(
        private string $limiter,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ($event->isMainRequest() && $request->isMethod('POST') && VerificationController::ROUTE === $request->attributes->get('_route') && !$request->attributes->has(self::ATTRIBUTE)) {
            $request->attributes->set(self::ATTRIBUTE, $this->limiter);
        }
    }
}
