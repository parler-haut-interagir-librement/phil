<?php

declare(strict_types=1);

namespace Phil\VideoBundle\Infrastructure\Cache;

use Override;
use Phil\VideoBundle\Application\Contract\ViewingStore;
use Phil\VideoBundle\Application\Heatmap;
use Phil\VideoBundle\Application\ViewingProgress;
use Phil\VideoBundle\Infrastructure\Memory\Counts;
use Psr\Cache\CacheItemPoolInterface;

/**
 * The heat maps kept in a cache pool of the application — Redis, a database —, one item per video, never expiring. A
 * viewing reads the item and writes it back: two viewings saved at the same instant may count as one, which a heat map
 * can bear; a store that must count every viewing implements `ViewingStore` on an atomic increment.
 */
final readonly class CacheViewingStore implements ViewingStore
{
    public function __construct(
        private CacheItemPoolInterface $pool,
    ) {
    }

    #[Override]
    public function add(ViewingProgress $progress): void
    {
        $item = $this->pool->getItem($this->key($progress->videoId));
        $heatmap = Counts::add($this->read($item->isHit() ? $item->get() : null), $progress);
        $item->set(['viewings' => $heatmap->viewings, 'counts' => $heatmap->counts]);
        $this->pool->save($item);
    }

    #[Override]
    public function heatmap(string $videoId): Heatmap
    {
        $item = $this->pool->getItem($this->key($videoId));

        return $this->read($item->isHit() ? $item->get() : null);
    }

    private function key(string $videoId): string
    {
        return 'phil_video.heatmap.'.$videoId;
    }

    private function read(mixed $value): Heatmap
    {
        if (!\is_array($value) || !\is_int($value['viewings'] ?? null) || !\is_array($value['counts'] ?? null)) {
            return new Heatmap();
        }

        $counts = [];

        for ($part = 0; $part < ViewingProgress::PARTS; ++$part) {
            $count = $value['counts'][$part] ?? null;
            $counts[] = \is_int($count) ? $count : 0;
        }

        return new Heatmap($value['viewings'], $counts);
    }
}
