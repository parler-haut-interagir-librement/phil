<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Infrastructure\Http;

use Phil\ErrorTrackingBundle\Application\BrowserErrorReceiver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The endpoint of the browsers' errors: reads one byte more than the bound of a body, never the whole of a larger
 * one, and answers with an empty response whose status tells the receipt — `204`, `400`, `403`, `413`, `415` or `429`.
 */
final readonly class BrowserErrorController
{
    public const string ROUTE = 'phil_error_tracking_browser';

    public const string TOKEN_HEADER = 'X-Error-Tracking-Token';

    public function __construct(
        private BrowserErrorReceiver $receiver,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $body = (string) stream_get_contents($request->getContent(true), $this->receiver->maxBytes() + 1);
        $receipt = $this->receiver->receive(
            $request->headers->get('Content-Type', ''),
            $body,
            $request->headers->get('Origin'),
            $request->getSchemeAndHttpHost(),
            $request->headers->get(self::TOKEN_HEADER),
            $request->getClientIp(),
            $request->headers->get('User-Agent'),
        );

        return new Response(null, $receipt->value, ['Cache-Control' => 'no-store']);
    }
}
