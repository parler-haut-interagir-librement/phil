<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogleBundle\Infrastructure\Http;

use JsonException as NativeJsonException;
use Override;
use Phil\SafeHttp\SafeResponse;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Component\HttpClient\Exception\RedirectionException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * An answer read by `phil/safe-http`, as Symfony HttpClient's contract gives it: with `$throw`, a `3xx`, `4xx` or `5xx`
 * status throws the matching exception of Symfony HttpClient.
 */
final readonly class SafeHttpResponse implements ResponseInterface
{
    public function __construct(
        private string $method,
        private SafeResponse $response,
    ) {
    }

    #[Override]
    public function getStatusCode(): int
    {
        return $this->response->status;
    }

    /**
     * @return array<string, list<string>>
     */
    #[Override]
    public function getHeaders(bool $throw = true): array
    {
        $this->check($throw);

        return $this->response->headers;
    }

    #[Override]
    public function getContent(bool $throw = true): string
    {
        $this->check($throw);

        return $this->response->body;
    }

    /**
     * @return array<array-key, mixed>
     */
    #[Override]
    public function toArray(bool $throw = true): array
    {
        $content = $this->getContent($throw);

        try {
            $decoded = json_decode($content, true, flags: \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);
        } catch (NativeJsonException $nativeJsonException) {
            throw new JsonException(\sprintf('The answer of "%s" is not JSON: %s.', $this->response->url->toString(), $nativeJsonException->getMessage()), $nativeJsonException->getCode(), previous: $nativeJsonException);
        }

        if (!\is_array($decoded)) {
            throw new JsonException(\sprintf('The answer of "%s" is not a JSON object.', $this->response->url->toString()));
        }

        return $decoded;
    }

    #[Override]
    public function cancel(): void
    {
    }

    #[Override]
    public function getInfo(?string $type = null): mixed
    {
        $lines = [\sprintf('HTTP/1.1 %d', $this->response->status)];

        foreach ($this->response->headers as $name => $values) {
            foreach ($values as $value) {
                $lines[] = $name.': '.$value;
            }
        }

        $info = [
            'http_code' => $this->response->status,
            'http_method' => $this->method,
            'url' => $this->response->url->toString(),
            'response_headers' => $lines,
            'redirect_count' => \count($this->response->redirects),
            'canceled' => false,
            'error' => null,
        ];

        return null === $type ? $info : $info[$type] ?? null;
    }

    private function check(bool $throw): void
    {
        $status = $this->response->status;

        if (!$throw || $status < 300) {
            return;
        }

        throw match (true) {
            $status < 400 => new RedirectionException($this),
            $status < 500 => new ClientException($this),
            default => new ServerException($this),
        };
    }
}
