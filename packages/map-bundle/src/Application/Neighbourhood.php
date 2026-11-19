<?php

declare(strict_types=1);

namespace Phil\MapBundle\Application;

use Phil\Geo\Coordinates;
use Phil\MapBundle\Domain\Exception\InvalidMap;
use Phil\MapBundle\Domain\ValueObject\ApproximateArea;
use Phil\MapBundle\Domain\ValueObject\PrivacyGrid;

/**
 * The neighbourhood layer: positions — members, places, groups — counted by cell of the privacy grid, and only the
 * cells that hold the minimum given back, as areas. No position given ever comes out, nor a cell of fewer.
 */
final readonly class Neighbourhood
{
    public const int MAX_POSITIONS = 100_000;

    public function __construct(
        private PrivacyGrid $grid,
    ) {
    }

    /**
     * The areas, the most populated first, then by row and column.
     *
     * @param iterable<Coordinates> $positions
     *
     * @return list<ApproximateArea>
     *
     * @throws InvalidMap when there are more positions than the bundle reads at once
     */
    public function areas(iterable $positions): array
    {
        $cells = [];
        $counts = [];
        $read = 0;

        foreach ($positions as $position) {
            if (++$read > self::MAX_POSITIONS) {
                throw InvalidMap::because(\sprintf('The neighbourhood layer reads %d positions at most.', self::MAX_POSITIONS));
            }

            $cell = $this->grid->cellOf($position);
            $key = $cell->key();
            $cells[$key] = $cell;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        $areas = [];

        foreach ($cells as $key => $cell) {
            if ($counts[$key] < $this->grid->minimum) {
                continue;
            }

            [$southWest, $northEast] = $this->grid->bounds($cell);
            $areas[] = [$cell, new ApproximateArea($southWest, $northEast, $this->grid->center($cell), $counts[$key])];
        }

        usort($areas, static fn (array $a, array $b): int => [$b[1]->count, $a[0]->row, $a[0]->column] <=> [$a[1]->count, $b[0]->row, $b[0]->column]);

        return array_map(static fn (array $pair): ApproximateArea => $pair[1], $areas);
    }
}
