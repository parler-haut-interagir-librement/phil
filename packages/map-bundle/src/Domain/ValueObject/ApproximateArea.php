<?php

declare(strict_types=1);

namespace Phil\MapBundle\Domain\ValueObject;

use Phil\Geo\Coordinates;

/**
 * A cell of the neighbourhood layer as it is shown: its corners, its middle and how many positions fell in it — never
 * one of the positions.
 */
final readonly class ApproximateArea
{
    public function __construct(
        public Coordinates $southWest,
        public Coordinates $northEast,
        public Coordinates $center,
        public int $count,
    ) {
    }
}
