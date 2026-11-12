<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\HttpClient;

use DateTimeImmutable;
use Generator;
use Override;
use Phil\ApiContract\Deprecation\DeprecationSignal;
use Phil\ApiContract\Deprecation\ObservedDeprecations;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\AsyncDecoratorTrait;
use Symfony\Component\HttpClient\Response\AsyncContext;
use Symfony\Component\HttpClient\Response\AsyncResponse;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Uri\WhatWg\Url;

/**
 * The HTTP client, watching for deprecation signals: when a response carries `Deprecation` or `Sunset`, the signal
 * is recorded for its endpoint and a warning logged once a day. The endpoint is the request's `extra.endpoint`
 * option — a route name, a method and path template — or else its method and path, each path segment that looks
 * like a value rather than a name replaced by `{id}` ({@see self::endpoint()}).
 *
 * That guess catches numbers, UUIDs, tokens, e-mail addresses and anything encoded, but not a value spelt like a
 * word — a username, a slug: `GET /users/jdoe` is recorded as it is. A client that calls such paths names its
 * endpoints with `extra.endpoint`, so that no identifier reaches the record nor the log.
 */
final class DeprecationObservingHttpClient implements HttpClientInterface
{
    use AsyncDecoratorTrait;

    /**
     * What a relative URL is read against: only its path is kept, so the host is one that resolves nowhere.
     */
    private const string BASE = 'http://endpoint.invalid/';

    public function __construct(
        HttpClientInterface $client,
        private readonly ObservedDeprecations $observed,
        private readonly ClockInterface $clock,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->client = $client;
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $extra = $options['extra'] ?? null;
        $endpoint = \is_array($extra) && \is_string($extra['endpoint'] ?? null) ? $extra['endpoint'] : self::endpoint($method, $url);

        return new AsyncResponse($this->client, $method, $url, $options, function (ChunkInterface $chunk, AsyncContext $context) use ($endpoint): Generator {
            if ($chunk->isFirst()) {
                /** @var array<string, list<string>> $headers lowercase names, each with its lines */
                $headers = $context->getHeaders();
                $this->observe($endpoint, $headers);
            }

            yield $chunk;
        });
    }

    /**
     * The method and the path, a segment kept only when it reads as a name: a letter, then letters, digits, `-` and
     * `_`, with at most two digits and 32 characters (`orders`, `v2`, `line-items`, `oauth2`). Any other non-empty segment — `42`,
     * a UUID, a hash, `jane.doe@example.com`, `caf%C3%A9`, `report.pdf` — becomes `{id}`.
     *
     * The path is the one a browser reads, by PHP's WHATWG URL parser, on which `phil/url` rests: a backslash is a
     * slash, the segments `.` and `..` are resolved, a character outside ASCII is percent-encoded, and user
     * information is never part of it. The client may call any host, an IP address or `localhost` included, so the
     * address is not held to `phil/url`'s rules for a public link. A relative URL, resolved later against the
     * client's `base_uri`, is read from the root; what cannot be read at all is the root.
     */
    public static function endpoint(string $method, string $url): string
    {
        $path = Url::parse($url, new Url(self::BASE))?->getPath() ?? '/';
        $path = str_starts_with($path, '/') ? $path : '/';

        $segments = array_map(static fn (string $segment): string => '' === $segment || self::isName($segment) ? $segment : '{id}', explode('/', $path));

        return strtoupper($method).' '.implode('/', $segments);
    }

    private static function isName(string $segment): bool
    {
        return 1 === preg_match('/\A[A-Za-z][A-Za-z0-9_-]{0,31}\z/', $segment) && preg_match_all('/\d/', $segment) <= 2;
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private function observe(string $endpoint, array $headers): void
    {
        $signal = DeprecationSignal::fromHeaders($headers['deprecation'][0] ?? null, $headers['sunset'][0] ?? null, $headers['link'] ?? []);

        if (!$signal instanceof DeprecationSignal) {
            return;
        }

        if ($this->observed->record($endpoint, $signal, $this->clock->now())) {
            $this->logger?->warning('The endpoint {endpoint} is deprecated{sunset}.', [
                'endpoint' => $endpoint,
                'sunset' => $signal->sunsetAt instanceof DateTimeImmutable ? ' and goes away on '.$signal->sunsetAt->format(\DATE_ATOM) : '',
                'link' => $signal->link,
            ]);
        }
    }
}
