<?php

declare(strict_types=1);

namespace Phil\VideoBundle\Infrastructure\Memory;

use Override;
use Phil\VideoBundle\Application\Contract\ViewingStore;
use Phil\VideoBundle\Application\Heatmap;
use Phil\VideoBundle\Application\ViewingProgress;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The heat maps of one request, for tests and development: emptied between requests, so that a worker keeps nothing
 * from one to the next.
 */
final class InMemoryViewingStore implements ViewingStore, ResetInterface
{
    /** @var array<string, Heatmap> */
    private array $heatmaps = [];

    #[Override]
    public function add(ViewingProgress $progress): void
    {
        $this->heatmaps[$progress->videoId] = Counts::add($this->heatmap($progress->videoId), $progress);
    }

    #[Override]
    public function heatmap(string $videoId): Heatmap
    {
        return $this->heatmaps[$videoId] ?? new Heatmap();
    }

    #[Override]
    public function reset(): void
    {
        $this->heatmaps = [];
    }
}
