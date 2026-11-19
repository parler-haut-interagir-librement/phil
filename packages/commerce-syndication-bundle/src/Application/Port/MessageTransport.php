<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Application\Port;

/**
 * Carries one signed message to a partner's endpoint.
 */
interface MessageTransport
{
    /**
     * Posts the body with its headers and answers the HTTP status received; `0` when no answer came — the endpoint
     * unreachable, the connection cut, the time out.
     *
     * @param array<string, string> $headers
     */
    public function send(string $url, array $headers, string $body): int;
}
