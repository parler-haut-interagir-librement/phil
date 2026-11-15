<?php

declare(strict_types=1);

namespace Phil\Transcription\Tests\Support;

use Closure;
use LogicException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * A transcription service played by a mock client: it answers each request with its response and keeps what it was
 * sent, the streamed body read to its end.
 */
final class RecordingClient
{
    /** @var list<array{method: string, url: string, headers: array<string, list<string>>, body: string}> */
    public array $requests = [];

    public readonly MockHttpClient $client;

    public function __construct(MockResponse ...$responses)
    {
        $this->client = new MockHttpClient(function (string $method, string $url, array $options) use (&$responses): MockResponse {
            $body = '';

            if (($options['body'] ?? null) instanceof Closure) {
                do {
                    $chunk = ($options['body'])(16_384);
                    $body .= \is_string($chunk) ? $chunk : '';
                } while (\is_string($chunk) && '' !== $chunk);
            } elseif (\is_string($options['body'] ?? null)) {
                $body = $options['body'];
            }

            /** @var array<string, list<string>> $headers */
            $headers = $options['normalized_headers'] ?? [];
            $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];

            return array_shift($responses) ?? new MockResponse('', ['http_code' => 500]);
        });
    }

    /**
     * @return array{method: string, url: string, headers: array<string, list<string>>, body: string}
     */
    public function last(): array
    {
        return array_last($this->requests) ?? throw new LogicException('No request was sent.');
    }
}
