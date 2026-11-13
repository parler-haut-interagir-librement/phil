<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Phil\PagedMedia\Gotenberg\Exception\InvalidStage;
use Phil\PagedMedia\Gotenberg\Internal\Check;

/**
 * A rectangle of a page, in points from its bottom left corner.
 */
final readonly class Region
{
    public function __construct(
        public int $page,
        public float $x,
        public float $y,
        public float $width,
        public float $height,
    ) {
        if ($page < 1) {
            throw InvalidStage::because(\sprintf('A region is on a page counted from 1; %d is not.', $page));
        }

        Check::above('The width of a region', $width, 0);
        Check::above('The height of a region', $height, 0);
    }

    /**
     * @return array{page: int, x: float, y: float, width: float, height: float}
     */
    public function toArray(): array
    {
        return ['page' => $this->page, 'x' => $this->x, 'y' => $this->y, 'width' => $this->width, 'height' => $this->height];
    }
}
