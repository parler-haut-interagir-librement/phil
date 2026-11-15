<?php

declare(strict_types=1);

namespace Phil\VideoBundle\Application\Contract;

use Phil\VideoBundle\Application\Heatmap;
use Phil\VideoBundle\Application\ViewingProgress;

/**
 * Where the heat maps of the videos are kept: a viewing adds one to each part it watched, and nothing of the viewing is
 * kept but those counts.
 */
interface ViewingStore
{
    public function add(ViewingProgress $progress): void;

    public function heatmap(string $videoId): Heatmap;
}
