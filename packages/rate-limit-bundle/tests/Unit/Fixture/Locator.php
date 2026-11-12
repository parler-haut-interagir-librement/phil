<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit\Fixture;

use Override;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * A container of the services it is given, by id.
 */
final readonly class Locator implements ContainerInterface
{
    /**
     * @param array<string, object> $services
     */
    public function __construct(
        private array $services,
    ) {
    }

    #[Override]
    public function get(string $id): object
    {
        return $this->services[$id] ?? throw new RuntimeException($id);
    }

    #[Override]
    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}
