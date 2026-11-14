<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Cache\Fixture;

use LogicException;
use Override;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * A tag-aware cache that takes no sub-namespace.
 */
final class TagAwareWithoutNamespaces implements TagAwareCacheInterface
{
    /**
     * @param array<array-key, mixed>|null $metadata
     */
    #[Override]
    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        throw new LogicException('The scoping of a pool never reads through it.');
    }

    #[Override]
    public function delete(string $key): bool
    {
        return true;
    }

    #[Override]
    public function invalidateTags(array $tags): bool
    {
        return true;
    }
}
