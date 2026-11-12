<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Adapter;

use Phil\CalendarSync\Exception\InvalidCalendarData;
use Phil\CalendarSync\Exception\ProviderFailed;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * One request of an adapter: no redirect followed — credentials never reach another host —, the silence and the whole
 * duration bounded, the body read in chunks up to a number of bytes, and the failures said without an address, a token
 * or a body.
 *
 * @internal
 */
final readonly class HttpCall
{
    public const int MAX_BYTES = 10 * 1024 * 1024;

    public function __construct(
        private HttpClientInterface $client,
        private string $provider,
        private float $timeout = 10.0,
        private float $maxDuration = 30.0,
        private int $maxBytes = self::MAX_BYTES,
    ) {
    }

    /**
     * @param array<string, mixed> $options  of Symfony's HttpClient: headers, body, json, auth, query
     * @param list<int>            $accepted statuses outside 2xx that are answers too: a `404` of a removal
     *
     * @return array{int, string, array<string, list<string>>} the status, the body and the headers
     *
     * @throws ProviderFailed      when the credentials are refused, the status is an error or the service does not answer
     * @throws InvalidCalendarData when the body is larger than the bytes allowed
     */
    public function send(string $method, string $url, array $options = [], array $accepted = []): array
    {
        try {
            // No proxy, not even one of the environment's: it would resolve the host itself, past the private network guard.
            $response = $this->client->request($method, $url, ['max_redirects' => 0, 'timeout' => $this->timeout, 'max_duration' => $this->maxDuration, 'proxy' => null, 'no_proxy' => '*'] + $options);
            // The status read first: an error status is an answer to read, not an exception of the stream.
            $status = $response->getStatusCode();
            $body = '';

            foreach ($this->client->stream($response) as $chunk) {
                $body .= $chunk->getContent();

                if (\strlen($body) > $this->maxBytes) {
                    $response->cancel();

                    throw InvalidCalendarData::because(\sprintf('An answer of a calendar service is %d bytes at most.', $this->maxBytes));
                }
            }

            $headers = $response->getHeaders(false);
        } catch (ExceptionInterface) {
            throw ProviderFailed::unreachable($this->provider);
        }

        if (401 === $status || 403 === $status) {
            throw ProviderFailed::unauthorized($this->provider, $status);
        }

        if (($status < 200 || $status >= 300) && !\in_array($status, $accepted, true)) {
            throw ProviderFailed::status($this->provider, $status);
        }

        return [$status, $body, $headers];
    }
}
