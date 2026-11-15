<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * The answer of a gateway that failed, for the tests of the adapters that report it.
 */
final class FailingResponse implements ResponseInterface
{
    #[Override]
    public function getStatusCode(): int
    {
        return 500;
    }

    #[Override]
    public function getHeaders(bool $throw = true): array
    {
        return [];
    }

    #[Override]
    public function getContent(bool $throw = true): string
    {
        return '';
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(bool $throw = true): array
    {
        return [];
    }

    #[Override]
    public function cancel(): void
    {
    }

    #[Override]
    public function getInfo(?string $type = null): mixed
    {
        return null;
    }
}
