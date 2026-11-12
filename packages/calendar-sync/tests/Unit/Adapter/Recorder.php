<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Tests\Unit\Adapter;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * A mock transport that answers in turn and keeps each request: its method, its address, its headers and its body.
 */
final class Recorder
{
    /** @var list<array{method: string, url: string, headers: list<string>, body: string}> */
    public array $requests = [];

    /** @var list<array<mixed>> the options of each request, as the client normalised them */
    public array $options = [];

    public MockHttpClient $client;

    /** @var list<MockResponse> */
    private array $responses;

    public function __construct(MockResponse ...$responses)
    {
        $this->responses = array_values($responses);
        $this->client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $headers = \is_array($options['headers'] ?? null) ? array_values(array_filter($options['headers'], is_string(...))) : [];
            $this->options[] = $options;
            $body = $options['body'] ?? '';
            $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => \is_string($body) ? $body : ''];

            return array_shift($this->responses) ?? new MockResponse('', ['http_code' => 599]);
        });
    }

    /**
     * Whether a request sent a header, `name: value`, the name in any case.
     */
    public function sent(int $request, string $header): bool
    {
        return \in_array(strtolower($header), array_map(strtolower(...), $this->requests[$request]['headers']), true);
    }
}
