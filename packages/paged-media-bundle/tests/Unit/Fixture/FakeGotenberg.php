<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Tests\Unit\Fixture;

use Closure;
use Phil\PagedMedia\Gotenberg\Gotenberg;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * A Gotenberg client whose server is `MockHttpClient`: each request is answered by the next response queued, and its
 * URL and multipart body are kept.
 */
final class FakeGotenberg
{
    /** @var list<array{url: string, body: string}> */
    public array $requests = [];

    /** @var list<ResponseInterface> */
    private array $responses = [];

    public readonly Gotenberg $client;

    public function __construct()
    {
        $this->client = new Gotenberg(new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = '';

            while (($options['body'] ?? null) instanceof Closure && \is_string($chunk = $options['body'](8192)) && '' !== $chunk) {
                $body .= $chunk;
            }

            $this->requests[] = ['url' => $url, 'body' => $body];

            return array_shift($this->responses) ?? new MockResponse('', ['http_code' => 500]);
        }), 'http://gotenberg:3000');
    }

    /**
     * @param array<string, string> $headers
     */
    public function answer(string $body = '%PDF-out', int $status = 200, array $headers = ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="out.pdf"']): self
    {
        $this->responses[] = new MockResponse($body, ['http_code' => $status, 'response_headers' => $headers]);

        return $this;
    }
}
