<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Infrastructure\Http;

use Phil\AnalyticsBundle\Application\Collection\CollectionOutcome;
use Phil\AnalyticsBundle\Application\Collection\CollectionRequest;
use Phil\AnalyticsBundle\Application\Collection\Collector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The collection endpoint, route `phil_analytics_collect`: one page view or event per `POST`, answered without a body.
 * A body announced longer than collection reads is refused before it is read; one sent without a length is read up to
 * one byte past the limit, never further.
 */
final readonly class CollectController
{
    public function __construct(
        private Collector $collector,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $length = $request->headers->get('Content-Length');

        $outcome = null !== $length && (int) $length > Collector::MAX_BODY_BYTES
            ? CollectionOutcome::TooLarge
            : $this->collector->collect(new CollectionRequest(
                $request->headers->get('Origin'),
                $request->headers->get('Referer'),
                (string) $request->getClientIp(),
                $request->headers->get('User-Agent', ''),
                $request->headers->get('DNT'),
                $request->headers->get('Sec-GPC'),
                $this->bodyOf($request),
            ));

        return new Response(null, $outcome->httpStatus(), ['Cache-Control' => 'no-store']);
    }

    /**
     * At most one byte more than collection reads: enough for the collector to refuse it.
     */
    private function bodyOf(Request $request): string
    {
        $body = stream_get_contents($request->getContent(true), Collector::MAX_BODY_BYTES + 1);

        return false === $body ? '' : $body;
    }
}
