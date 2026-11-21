<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogle\Infrastructure\Http;

use Generator;
use LogicException;
use Override;
use Phil\SafeHttp\Exception\SafeHttpException;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\ReadRule;
use Phil\SafeHttp\SafeHttpClient;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\Response\ResponseStream;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The HTTP client Symfony's OpenID Connect pieces are given, sending every request through `phil/safe-http`: the
 * address checked and the private networks refused, no redirect followed, the time and the bytes bounded. It takes the
 * options those pieces use — a form `body`, `headers`, `auth_basic`, `auth_bearer` and `max_redirects` — and refuses
 * any other, so that an option it would drop is never silently ignored. The answer is read whole, within the limits,
 * and `stream()` gives it back in one chunk.
 */
final readonly class SafeHttpTransport implements HttpClientInterface, ResetInterface
{
    /** What the answers of a provider are: a discovery document, a key set, a token response. */
    private const string ACCEPT = 'application/json, application/jwk-set+json;q=0.9, */*;q=0.1';

    private const array OPTIONS = ['body', 'headers', 'auth_basic', 'auth_bearer', 'max_redirects'];

    public function __construct(
        private SafeHttpClient $client,
    ) {
    }

    /**
     * Limits for a provider's answers: 5 seconds of silence, 10 in all, 1 MiB, no redirect.
     */
    public static function limits(string $userAgent = 'PhilIdentityProviderGoogle/0.1'): Limits
    {
        return new Limits(timeout: 5.0, maxDuration: 10.0, maxBytes: 1_048_576, maxRedirects: 0, userAgent: $userAgent);
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $unknown = array_diff(array_keys($options), self::OPTIONS);

        if ([] !== $unknown) {
            throw new InvalidArgumentException(\sprintf('The option "%s" is not supported.', implode('", "', $unknown)));
        }

        $headers = $this->headers($options['headers'] ?? []);
        $body = $options['body'] ?? '';

        if (\is_array($body)) {
            $body = http_build_query($body, '', '&');
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        if (!\is_string($body)) {
            throw new InvalidArgumentException('The body is a string or a form.');
        }

        if (isset($options['auth_basic'])) {
            $headers['Authorization'] = 'Basic '.sodium_bin2base64($this->credential($options['auth_basic']), \SODIUM_BASE64_VARIANT_ORIGINAL);
        }

        if (isset($options['auth_bearer'])) {
            $headers['Authorization'] = 'Bearer '.$this->credential($options['auth_bearer']);
        }

        try {
            return new SafeHttpResponse($method, $this->client->send($method, $url, $headers, $body, new ReadRule(self::ACCEPT)));
        } catch (SafeHttpException $safeHttpException) {
            throw new TransportException($safeHttpException->getMessage(), $safeHttpException->getCode(), previous: $safeHttpException);
        }
    }

    /**
     * The answers given by this client, each already read whole: a first chunk, its body and a last chunk.
     */
    #[Override]
    public function stream(iterable|ResponseInterface $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return new ResponseStream($this->chunks($responses instanceof ResponseInterface ? [$responses] : $responses));
    }

    /**
     * Not offered: the options come with each request.
     *
     * @param array<array-key, mixed> $options
     */
    #[Override]
    public function withOptions(array $options): static
    {
        throw new LogicException('The options of this client come with each request.');
    }

    #[Override]
    public function reset(): void
    {
        $this->client->reset();
    }

    /**
     * @param iterable<ResponseInterface> $responses
     *
     * @return Generator<ResponseInterface, ChunkInterface>
     */
    private function chunks(iterable $responses): Generator
    {
        foreach ($responses as $response) {
            if (!$response instanceof SafeHttpResponse) {
                throw new InvalidArgumentException('Only the answers of this client are streamed.');
            }

            $content = $response->getContent(false);
            yield $response => new ReadChunk(first: true);
            yield $response => new ReadChunk($content);
            yield $response => new ReadChunk(offset: \strlen($content), last: true);
        }
    }

    private function credential(mixed $credential): string
    {
        if (!\is_string($credential) || '' === $credential) {
            throw new InvalidArgumentException('A credential is a string.');
        }

        return $credential;
    }

    /**
     * @return array<string, string>
     */
    private function headers(mixed $headers): array
    {
        if (!\is_array($headers)) {
            throw new InvalidArgumentException('The headers are a list.');
        }

        $read = [];

        foreach ($headers as $name => $value) {
            if (\is_int($name) && \is_string($value) && str_contains($value, ':')) {
                [$name, $value] = explode(':', $value, 2);
            }

            if (!\is_string($name) || !\is_string($value)) {
                throw new InvalidArgumentException('A header is a name and a string.');
            }

            $read[trim($name)] = trim($value);
        }

        return $read;
    }
}
