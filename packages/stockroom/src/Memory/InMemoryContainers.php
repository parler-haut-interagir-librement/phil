<?php

declare(strict_types=1);

namespace Phil\Stockroom\Memory;

use Override;
use Phil\Stockroom\Container;
use Phil\Stockroom\Contract\Containers;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The containers in memory, for tests and short scripts; emptied by `reset()` between the requests of a worker.
 */
final class InMemoryContainers implements Containers, ResetInterface
{
    /** @var array<string, Container> */
    private array $containers = [];

    #[Override]
    public function get(string $id): ?Container
    {
        return $this->containers[$id] ?? null;
    }

    #[Override]
    public function save(Container $container): void
    {
        $this->containers[$container->id] = $container;
    }

    #[Override]
    public function children(string $id, int $limit): array
    {
        $children = array_filter($this->containers, static fn (Container $container): bool => $id === $container->parent);
        ksort($children, \SORT_STRING);

        return \array_slice(array_values($children), 0, max(0, $limit));
    }

    #[Override]
    public function reset(): void
    {
        $this->containers = [];
    }
}
