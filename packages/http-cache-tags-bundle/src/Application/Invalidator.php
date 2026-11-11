<?php

declare(strict_types=1);

namespace Phil\HttpCacheTagsBundle\Application;

use Phil\HttpCacheTagsBundle\Domain\ValueObject\CacheTag;

/**
 * The tags to purge, gathered during a request or a command and purged once, at its end, each tag once.
 */
final class Invalidator
{
    /** @var array<string, CacheTag> */
    private array $pending = [];

    /**
     * @param iterable<CacheTagResolver> $resolvers
     */
    public function __construct(
        private readonly ?CacheTagPurger $purger,
        private readonly iterable $resolvers = [],
    ) {
    }

    public function invalidate(string ...$tags): void
    {
        foreach (CacheTag::list($tags) as $tag) {
            $this->pending[$tag->value] = $tag;
        }
    }

    /**
     * Invalidates the tags of the responses that show an entity.
     */
    public function invalidateEntity(object $entity): void
    {
        foreach ($this->resolvers as $resolver) {
            $this->invalidate(...$resolver->tagsFor($entity));
        }
    }

    /**
     * @return list<string>
     */
    public function pending(): array
    {
        return array_keys($this->pending);
    }

    /**
     * Purges the pending tags; returns how many.
     */
    public function flush(): int
    {
        $tags = array_values($this->pending);
        $this->pending = [];

        if ([] === $tags || !$this->purger instanceof CacheTagPurger) {
            return 0;
        }

        $this->purger->purge($tags);

        return \count($tags);
    }
}
