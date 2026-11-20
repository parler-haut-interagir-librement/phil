<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Infrastructure\Http;

use Override;
use Phil\CommerceSyndicationBundle\Application\Port\MessageTransport;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The transport over Symfony's HTTP client: a `POST` that follows no redirect and waits ten seconds at most.
 */
final readonly class HttpClientTransport implements MessageTransport
{
    public function __construct(
        private HttpClientInterface $client,
    ) {
    }

    #[Override]
    public function send(string $url, array $headers, string $body): int
    {
        try {
            return $this->client->request('POST', $url, ['headers' => $headers, 'body' => $body, 'max_redirects' => 0, 'timeout' => 10])->getStatusCode();
        } catch (TransportExceptionInterface) {
            return 0;
        }
    }
}
