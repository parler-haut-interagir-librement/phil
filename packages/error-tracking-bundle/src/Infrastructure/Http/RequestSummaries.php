<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Infrastructure\Http;

use Phil\ErrorTracking\RequestSummary;
use Phil\IpAnonymizer\Contract\IpAnonymizer;
use Symfony\Component\HttpFoundation\Request;

/**
 * The summary of a Symfony request: its method, its address, its route, its headers each joined on one line, and the
 * client's address through the anonymizer. The body is never read; the headers that carry the client's raw address
 * through proxies, `X-Forwarded-For`, `Forwarded`, `X-Real-IP` and the others, are dropped by the summary.
 */
final readonly class RequestSummaries
{
    public function __construct(
        private IpAnonymizer $anonymizer,
    ) {
    }

    public function summarize(Request $request): RequestSummary
    {
        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = implode(', ', array_filter($values, \is_string(...)));
        }

        $route = $request->attributes->get('_route');

        return RequestSummary::anonymized($request->getMethod(), $request->getUri(), \is_string($route) ? $route : null, $headers, $request->getClientIp(), $this->anonymizer);
    }
}
