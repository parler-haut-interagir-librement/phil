<?php

declare(strict_types=1);

namespace Phil\Stockroom\Contract;

use Phil\Stockroom\Container;

/**
 * Where the containers are kept.
 */
interface Containers
{
    public function get(string $id): ?Container;

    /**
     * Adds the container, or replaces the one of its identifier.
     */
    public function save(Container $container): void;

    /**
     * The containers that sit directly in a container, at most so many, in the order of their identifiers.
     *
     * @return list<Container>
     */
    public function children(string $id, int $limit): array;
}
