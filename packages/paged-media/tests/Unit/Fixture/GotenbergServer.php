<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Tests\Unit\Fixture;

use Closure;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * A Gotenberg played by `MockHttpClient`: it answers each request with the next response queued, and keeps what was
 * sent — the method, the URL, the headers, the options, and the multipart body read back into fields and files.
 */
final class GotenbergServer
{
    /** @var list<array{method: string, url: string, headers: array<string, list<string>>, options: array<array-key, mixed>, fields: array<string, list<string>>, files: array<string, list<array{string, string}>>}> */
    public array $requests = [];

    /** @var list<ResponseInterface> */
    private array $responses = [];

    public readonly MockHttpClient $client;

    public function __construct()
    {
        $this->client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $headers = $this->headers($options['normalized_headers'] ?? null);
            [$fields, $files] = $this->multipart($headers['content-type'][0] ?? '', $this->drain($options['body'] ?? null));
            $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'options' => $options, 'fields' => $fields, 'files' => $files];

            return array_shift($this->responses) ?? new MockResponse('', ['http_code' => 500]);
        });
    }

    /**
     * @param array<string, string> $headers
     */
    public function answer(string $body = '%PDF-1.7', int $status = 200, array $headers = ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="report.pdf"']): self
    {
        $this->responses[] = new MockResponse($body, ['http_code' => $status, 'response_headers' => $headers]);

        return $this;
    }

    public function answerWith(ResponseInterface $response): self
    {
        $this->responses[] = $response;

        return $this;
    }

    /**
     * @param array<array-key, mixed> $json
     */
    public function answerJson(array $json): self
    {
        return $this->answer(json_encode($json, \JSON_THROW_ON_ERROR), 200, ['Content-Type' => 'application/json']);
    }

    /**
     * @return array{method: string, url: string, headers: array<string, list<string>>, options: array<array-key, mixed>, fields: array<string, list<string>>, files: array<string, list<array{string, string}>>}
     */
    public function last(): array
    {
        return $this->requests[array_key_last($this->requests) ?? 0];
    }

    private function drain(mixed $body): string
    {
        $drained = '';

        while ($body instanceof Closure && \is_string($chunk = $body(8192)) && '' !== $chunk) {
            $drained .= $chunk;
        }

        return $drained;
    }

    /**
     * The headers sent, each value without its name.
     *
     * @return array<string, list<string>>
     */
    private function headers(mixed $normalized): array
    {
        $headers = [];

        foreach (\is_array($normalized) ? $normalized : [] as $name => $lines) {
            foreach (\is_array($lines) ? $lines : [] as $line) {
                if (\is_string($line)) {
                    $headers[(string) $name][] = substr($line, \strlen((string) $name) + 2);
                }
            }
        }

        return $headers;
    }

    /**
     * @return array{array<string, list<string>>, array<string, list<array{string, string}>>}
     */
    private function multipart(string $contentType, string $body): array
    {
        $fields = [];
        $files = [];

        if (!str_starts_with($contentType, 'multipart/form-data; boundary=')) {
            return [$fields, $files];
        }

        $boundary = substr($contentType, \strlen('multipart/form-data; boundary='));

        foreach (\array_slice(explode('--'.$boundary, $body), 1, -1) as $part) {
            [$head, $content] = explode("\r\n\r\n", substr($part, 2, -2), 2);
            preg_match('/name="([^"]*)"(?:; filename="([^"]*)")?/', $head, $match);
            $match += ['', '', null];

            if (null !== $match[2]) {
                $files[$match[1]][] = [$match[2], $content];
            } else {
                $fields[$match[1]][] = $content;
            }
        }

        return [$fields, $files];
    }
}
