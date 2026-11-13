<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Tests\Functional\App;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Plays Gotenberg and the vault for the application's HTTP client: the version, the vault's secret, and a PDF for any
 * other request, which it keeps.
 */
final class GotenbergResponses
{
    /** @var list<array{string, string, array<array-key, mixed>}> */
    public array $requests = [];

    /**
     * @param array<array-key, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options): ResponseInterface
    {
        $this->requests[] = [$method, $url, $options];

        return match (true) {
            str_ends_with($url, '/version') => new MockResponse('9.1.0'),
            str_contains($url, '/v1/') => new MockResponse('{"data":{"data":{"certificate":"C","key":"K"}}}', ['response_headers' => ['Content-Type' => 'application/json']]),
            default => new MockResponse('%PDF-gotenberg', ['response_headers' => ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="out.pdf"']]),
        };
    }
}
