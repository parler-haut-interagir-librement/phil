<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Fixture;

use Phil\ContentBundle\Application\Content\ContentRootFactory;
use Phil\ContentBundle\Domain\Content\ContentRoot;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\Repository\ContentRootRepository;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;

/**
 * Example roots of the resource key `example`, numbered in the order they are flushed; also their factory.
 * `add()` and `remove()` wait for `flush()`, as on the entity manager.
 */
final class InMemoryContentRootRepository implements ContentRootRepository, ContentRootFactory
{
    /**
     * Numbered from 0; a removed root leaves `null`, so the others keep their number.
     *
     * @var list<ContentRoot|null>
     */
    private array $roots = [];

    /**
     * @var list<ContentRoot>
     */
    private array $added = [];

    /**
     * @var list<ContentRoot>
     */
    private array $removed = [];

    public function create(string $resourceKey): ContentRoot
    {
        if ('example' !== $resourceKey) {
            throw InvalidContent::because(\sprintf('Unknown resource key "%s".', $resourceKey));
        }

        return new ExampleRoot();
    }

    public function find(ContentReference $reference): ?ContentRoot
    {
        return 'example' === $reference->resourceKey ? ($this->roots[(int) $reference->resourceId - 1] ?? null) : null;
    }

    public function findAll(string $resourceKey): array
    {
        return 'example' === $resourceKey ? array_values(array_filter($this->roots)) : [];
    }

    public function referenceOf(ContentRoot $root): ContentReference
    {
        $index = array_search($root, $this->roots, true);

        if (false === $index) {
            throw InvalidContent::because('The root was never added.');
        }

        return ContentReference::of('example', (string) ($index + 1));
    }

    /**
     * Like a root whose identifier the database generates, an added root has a reference once flushed.
     */
    public function add(ContentRoot $root): void
    {
        $this->added[] = $root;
    }

    public function remove(ContentRoot $root): void
    {
        $this->removed[] = $root;
    }

    public function flush(): void
    {
        $this->roots = [...$this->roots, ...$this->added];
        $this->added = [];

        foreach ($this->removed as $removed) {
            $index = array_search($removed, $this->roots, true);

            if (false !== $index) {
                $this->roots[$index] = null;
            }
        }

        $this->removed = [];
    }
}
