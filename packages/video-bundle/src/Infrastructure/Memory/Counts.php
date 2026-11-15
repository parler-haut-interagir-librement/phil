<?php

declare(strict_types=1);

namespace Phil\VideoBundle\Infrastructure\Memory;

use Phil\VideoBundle\Application\Heatmap;
use Phil\VideoBundle\Application\ViewingProgress;

/**
 * A heat map with one viewing more.
 *
 * @internal
 */
final class Counts
{
    private function __construct()
    {
    }

    public static function add(Heatmap $heatmap, ViewingProgress $progress): Heatmap
    {
        $counts = array_pad($heatmap->counts, ViewingProgress::PARTS, 0);

        foreach ($progress->parts as $part) {
            ++$counts[$part];
        }

        return new Heatmap($heatmap->viewings + 1, array_values($counts));
    }
}
