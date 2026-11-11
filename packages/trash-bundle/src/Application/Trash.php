<?php

declare(strict_types=1);

namespace Phil\TrashBundle\Application;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Phil\TrashBundle\Application\Event\ResourceRestored;
use Phil\TrashBundle\Application\Event\ResourceTrashed;
use Phil\TrashBundle\Application\Event\TrashItemPurged;
use Phil\TrashBundle\Domain\Exception\ParentMissing;
use Phil\TrashBundle\Domain\Exception\UnknownResource;
use Phil\TrashBundle\Domain\Model\TrashItem;
use Phil\TrashBundle\Domain\Repository\TrashItemRepository;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The trash: a resource goes in by its handler, which keeps what it needs and removes it; it comes back by the same
 * handler, under its parent or another one when its parent is gone; it goes for good once its retention is over.
 * Each step is an event, for an audit trail.
 */
final readonly class Trash
{
    /**
     * @param iterable<TrashHandler> $handlers
     * @param array<string, int>     $retention days kept, by kind
     */
    public function __construct(
        private TrashItemRepository $items,
        private iterable $handlers,
        private ClockInterface $clock,
        private int $defaultRetention = 30,
        private array $retention = [],
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    public function trash(object $resource, ?string $trashedBy = null): TrashItem
    {
        $handler = $this->handlerOf($resource);
        $captured = $handler->capture($resource);
        $now = $this->now();
        $key = $handler->resourceKey();
        $item = TrashItem::of(ResourceReference::of($key, $captured->id), $captured->title, $captured->payload, $captured->parent, $now, $trashedBy, $now->modify(\sprintf('+%d days', $this->retention[$key] ?? $this->defaultRetention)));

        $handler->remove($resource);
        $this->items->save($item);
        $this->events?->dispatch(new ResourceTrashed($item));

        return $item;
    }

    /**
     * Brings a resource back, under its own parent, or under another one: its parent may be gone.
     *
     * @throws ParentMissing when the parent it comes back under is gone
     */
    public function restore(TrashItem $item, ?ResourceReference $parent = null): object
    {
        $handler = $this->handlerFor($item->resource()->key);
        $parent ??= $item->parent();

        if ($parent instanceof ResourceReference && !$handler->parentExists($parent)) {
            throw ParentMissing::of($item->resource(), $parent);
        }

        $resource = $handler->restore($item, $parent);
        $this->items->remove($item);
        $this->events?->dispatch(new ResourceRestored($item, $resource));

        return $resource;
    }

    /**
     * Forgets for good the items whose retention is over, at most so many.
     *
     * @return int how many were purged
     */
    public function purge(int $limit): int
    {
        $purged = 0;

        foreach ($this->items->expired($this->now(), $limit) as $item) {
            $this->handlerOrNull($item->resource()->key)?->purge($item);
            $this->items->remove($item);
            $this->events?->dispatch(new TrashItemPurged($item));
            ++$purged;
        }

        return $purged;
    }

    private function handlerOf(object $resource): TrashHandler
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($resource)) {
                return $handler;
            }
        }

        throw UnknownResource::object($resource);
    }

    private function handlerFor(string $key): TrashHandler
    {
        return $this->handlerOrNull($key) ?? throw UnknownResource::kind($key);
    }

    private function handlerOrNull(string $key): ?TrashHandler
    {
        foreach ($this->handlers as $handler) {
            if ($handler->resourceKey() === $key) {
                return $handler;
            }
        }

        return null;
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock->now();
    }
}
