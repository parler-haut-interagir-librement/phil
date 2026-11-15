<?php

declare(strict_types=1);

namespace Phil\Lti\Http;

/**
 * The answer of a service: its status, its headers by lowercase name, and its body.
 */
final readonly class ServiceResponse
{
    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }

    /**
     * @return list<string>
     */
    public function headers(string $name): array
    {
        return $this->headers[strtolower($name)] ?? [];
    }

    public function isSuccessful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}
