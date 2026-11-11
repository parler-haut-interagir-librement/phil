<?php

declare(strict_types=1);

namespace Phil\TrashBundle\Tests\Unit\Fixtures;

use LogicException;
use Phil\Resource\ResourceReference;
use Phil\TrashBundle\Application\Captured;
use Phil\TrashBundle\Application\TrashHandler;
use Phil\TrashBundle\Domain\Model\TrashItem;

/**
 * Pages in memory, under parents in memory.
 */
final class PageHandler implements TrashHandler
{
    /** @var array<array-key, Page> */
    public array $pages = [];

    public function page(string $id): Page
    {
        return $this->pages[$id] ?? throw new LogicException('No page '.$id);
    }

    public function forget(string $id): void
    {
        unset($this->pages[$id]);
    }

    /** @var list<string> */
    public array $purged = [];

    public function resourceKey(): string
    {
        return 'page';
    }

    public function supports(object $resource): bool
    {
        return $resource instanceof Page;
    }

    public function capture(object $resource): Captured
    {
        \assert($resource instanceof Page);

        return new Captured($resource->id, $resource->title, ['title' => $resource->title], null === $resource->parent ? null : ResourceReference::of('page', $resource->parent));
    }

    public function remove(object $resource): void
    {
        \assert($resource instanceof Page);
        unset($this->pages[$resource->id]);
    }

    public function parentExists(ResourceReference $parent): bool
    {
        return isset($this->pages[$parent->id]);
    }

    public function restore(TrashItem $item, ?ResourceReference $parent): Page
    {
        $title = $item->payload()['title'] ?? '';
        \assert(\is_string($title));

        return $this->pages[$item->resource()->id] = new Page($item->resource()->id, $title, $parent?->id);
    }

    public function purge(TrashItem $item): void
    {
        $this->purged[] = $item->resource()->id;
    }
}
