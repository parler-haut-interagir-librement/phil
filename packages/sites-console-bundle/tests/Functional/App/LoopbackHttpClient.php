<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Functional\App;

use Closure;
use Override;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * The console's HTTP client in the test application: each request is handled by the application itself, whose
 * agent answers it — the console and the site in one kernel.
 */
final readonly class LoopbackHttpClient implements HttpClientInterface
{
    private MockHttpClient $client;

    /**
     * @param Closure(): HttpKernelInterface $kernel
     */
    public function __construct(Closure $kernel)
    {
        $this->client = new MockHttpClient(static function (string $method, string $url, array $options) use ($kernel): MockResponse {
            $request = Request::create($url, $method, content: \is_string($options['body'] ?? null) ? $options['body'] : '');

            foreach (\is_array($options['headers'] ?? null) ? $options['headers'] : [] as $header) {
                [$name, $value] = explode(': ', \is_string($header) ? $header : '', 2) + [1 => ''];
                $request->headers->set($name, $value);
            }

            $response = $kernel()->handle($request, HttpKernelInterface::SUB_REQUEST);

            return new MockResponse((string) $response->getContent(), ['http_code' => $response->getStatusCode(), 'response_headers' => $response->headers->allPreserveCase()]);
        });
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        return $this->client->request($method, $url, $options);
    }

    #[Override]
    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->client->stream($responses, $timeout);
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    public function withOptions(array $options): static
    {
        return $this;
    }
}
