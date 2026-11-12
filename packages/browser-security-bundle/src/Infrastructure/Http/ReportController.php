<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Infrastructure\Http;

use Phil\BrowserSecurityBundle\Application\ReportReceiver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The endpoint of the reports: reads one byte more than the bound of a body, never the whole of a larger one, and
 * answers with an empty response whose status tells the receipt — `204`, `400`, `413`, `415` or `429`.
 */
final readonly class ReportController
{
    public function __construct(
        private ReportReceiver $receiver,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $body = (string) stream_get_contents($request->getContent(true), $this->receiver->maxBytes() + 1);
        $receipt = $this->receiver->receive($request->headers->get('Content-Type', ''), $body, $request->getClientIp());

        return new Response(null, $receipt->value, ['Cache-Control' => 'no-store']);
    }
}
