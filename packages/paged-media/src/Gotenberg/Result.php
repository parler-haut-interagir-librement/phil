<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg;

use Phil\PagedMedia\Gotenberg\Exception\InvalidRequest;
use Phil\PagedMedia\Gotenberg\Exception\ServiceUnavailable;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * What a route that produces files answered: one file — a PDF, an image, a text — or a ZIP of several, read whole or
 * streamed to a file; or nothing yet, when a webhook receives the result. It carries the remediation counts when a
 * `remediate` stage ran.
 */
final readonly class Result
{
    /**
     * @param string|null $filename   the result's name, from `Content-Disposition`
     * @param string|null $trace      the request's identifier in Gotenberg's logs
     * @param string|null $version    the version of Gotenberg that answered
     * @param int|null    $residual   after `remediate`: the violations left, `0` when every document conforms
     * @param int|null    $rolledBack after `remediate`: the fixes undone because they lost pages, text or images
     */
    public function __construct(
        private HttpClientInterface $client,
        private ResponseInterface $response,
        public bool $deferred,
        public string $contentType,
        public ?string $filename,
        public ?string $trace,
        public ?string $version,
        public ?int $residual,
        public ?int $rolledBack,
    ) {
    }

    /**
     * @internal
     */
    public static function from(HttpClientInterface $client, ResponseInterface $response): self
    {
        $headers = $response->getHeaders();
        $header = static fn (string $name): ?string => $headers[$name][0] ?? null;
        $count = static fn (string $name): ?int => null !== $header($name) && ctype_digit($header($name)) ? (int) $header($name) : null;

        return new self(
            $client,
            $response,
            204 === $response->getStatusCode(),
            $header('content-type') ?? '',
            self::filename($header('content-disposition') ?? ''),
            $header('gotenberg-trace'),
            $header('gotenberg-version'),
            $count('gotenberg-remediation-residual'),
            $count('gotenberg-remediation-rolled-back'),
        );
    }

    /**
     * Whether several files came back in a ZIP.
     */
    public function isZip(): bool
    {
        return str_starts_with($this->contentType, 'application/zip');
    }

    /**
     * After `remediate`: whether every document conforms to every profile; null when no remediation ran.
     */
    public function conforms(): ?bool
    {
        return null === $this->residual ? null : 0 === $this->residual;
    }

    /**
     * The result's bytes; empty for a deferred result.
     */
    public function contents(): string
    {
        try {
            return $this->response->getContent();
        } catch (ExceptionInterface $exception) {
            throw ServiceUnavailable::unreachable($this->url(), $exception);
        }
    }

    /**
     * Writes the result to a file as it arrives, without holding it whole in memory.
     */
    public function saveTo(string $path): void
    {
        if (false === @file_put_contents($path, '')) {
            throw InvalidRequest::because(\sprintf('The file "%s" cannot be written.', $path));
        }

        try {
            foreach ($this->client->stream($this->response) as $chunk) {
                file_put_contents($path, $chunk->getContent(), \FILE_APPEND);
            }
        } catch (ExceptionInterface $exception) {
            throw ServiceUnavailable::unreachable($this->url(), $exception);
        }
    }

    private function url(): string
    {
        $url = $this->response->getInfo('url');

        return \is_string($url) ? $url : '';
    }

    /**
     * The name a `Content-Disposition` header gives: the UTF-8 `filename*` when there is one, else `filename`.
     */
    private static function filename(string $disposition): ?string
    {
        $plain = null;

        foreach (explode(';', $disposition) as $parameter) {
            [$key, $value] = array_map(trim(...), explode('=', $parameter, 2)) + ['', ''];

            if ('filename*' === strtolower($key) && str_starts_with(strtolower($value), "utf-8''")) {
                return rawurldecode(substr($value, 7));
            }

            if ('filename' === strtolower($key)) {
                $plain = trim($value, '"');
            }
        }

        return $plain;
    }
}
