<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Memory;

/**
 * What a store in memory hands out and keeps: copies, as a database does, so that a change reaches the store only
 * through a save.
 */
trait Snapshots
{
    /**
     * @template T of object
     *
     * @param T|null $object
     *
     * @return T|null
     */
    private static function copy(?object $object): ?object
    {
        return null === $object ? null : clone $object;
    }

    /**
     * @template T of object
     *
     * @param list<T> $objects
     *
     * @return list<T>
     */
    private static function copies(array $objects): array
    {
        return array_map(static fn (object $object): object => clone $object, $objects);
    }
}
