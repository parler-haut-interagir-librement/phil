<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Probe;

use Override;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The transport under phil/safe-http, which keeps the answers of one check in order, so that the codes of its redirects
 * can be read: phil/safe-http follows them and returns only the addresses. `HttpLinkProbe` makes it forget them after each check.
 *
 * @internal
 */
final class HopRecorder implements HttpClientInterface, ResetInterface
{
    /** One check makes at most two requests, each followed by the redirects phil/safe-http allows. */
    private const int MAX_ANSWERS = 32;

    /** @var list<ResponseInterface> */
    private array $answers = [];

    public function __construct(
        private HttpClientInterface $client,
    ) {
    }

    /**
     * @param array<mixed> $options
     */
    #[Override]
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $response = $this->client->request($method, $url, $options);

        if (\count($this->answers) < self::MAX_ANSWERS) {
            $this->answers[] = $response;
        }

        return $response;
    }

    #[Override]
    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->client->stream($responses, $timeout);
    }

    /**
     * @param array<mixed> $options
     */
    #[Override]
    public function withOptions(array $options): static
    {
        $clone = clone $this;
        $clone->client = $this->client->withOptions($options);
        $clone->answers = [];

        return $clone;
    }

    /**
     * The HTTP codes of the answers recorded since the last reset, in order.
     *
     * @return list<int>
     */
    public function codes(): array
    {
        return array_map(static function (ResponseInterface $response): int {
            $code = $response->getInfo('http_code');

            return \is_int($code) ? $code : 0;
        }, $this->answers);
    }

    /**
     * Forgets the answers recorded, between two checks.
     */
    public function forget(): void
    {
        $this->answers = [];
    }

    #[Override]
    public function reset(): void
    {
        $this->answers = [];

        if ($this->client instanceof ResetInterface) {
            $this->client->reset();
        }
    }
}
