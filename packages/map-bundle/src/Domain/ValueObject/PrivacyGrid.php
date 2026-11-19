<?php

declare(strict_types=1);

namespace Phil\MapBundle\Domain\ValueObject;

use Phil\Geo\Coordinates;
use Phil\MapBundle\Domain\Exception\InvalidMap;

/**
 * The fixed grid of the neighbourhood layer: cells about as many metres high and wide, rows from the equator and
 * columns from the prime meridian, so that a position always falls in the same cell. A position is only ever shown as
 * its cell, and a cell only when it holds at least the minimum of positions.
 *
 * The grid is fixed rather than random: noise added to each answer averages out over repeated requests, a fixed cell
 * does not. A column's width follows the latitude of its row, so cells keep their size away from the equator; above
 * about 89.4° they stop narrowing.
 */
final readonly class PrivacyGrid
{
    public const int MIN_METRES = 100;

    public const int MAX_METRES = 50_000;

    public const int MAX_MINIMUM = 1000;

    /** Metres in one degree of latitude, and of longitude at the equator. */
    private const float METRES_PER_DEGREE = 111_320.0;

    /** The cosine below which columns stop narrowing, near the poles. */
    private const float MIN_COSINE = 0.01;

    private function __construct(
        public int $metres,
        public int $minimum,
    ) {
    }

    /**
     * @param int $metres  the side of a cell, from 100 m to 50 km
     * @param int $minimum the fewest positions a cell holds to be shown, from 1 to 1000
     *
     * @throws InvalidMap when a value is out of its bounds
     */
    public static function of(int $metres = 1000, int $minimum = 3): self
    {
        if ($metres < self::MIN_METRES || $metres > self::MAX_METRES) {
            throw InvalidMap::because(\sprintf('A cell of the grid is %d to %d metres wide, not %d.', self::MIN_METRES, self::MAX_METRES, $metres));
        }

        if ($minimum < 1 || $minimum > self::MAX_MINIMUM) {
            throw InvalidMap::because(\sprintf('A cell is shown with 1 to %d positions at least, not %d.', self::MAX_MINIMUM, $minimum));
        }

        return new self($metres, $minimum);
    }

    /**
     * The cell a position falls in.
     */
    public function cellOf(Coordinates $position): GridCell
    {
        $height = $this->latitudeStep();
        $row = (int) floor($position->latitude / $height);

        return new GridCell($row, (int) floor($position->longitude / $this->longitudeStep($row)));
    }

    /**
     * The corners of a cell, south-west then north-east, clamped to the latitudes and longitudes that exist.
     *
     * @return array{Coordinates, Coordinates}
     */
    public function bounds(GridCell $cell): array
    {
        $height = $this->latitudeStep();
        $width = $this->longitudeStep($cell->row);

        return [
            Coordinates::of(max(-90.0, $cell->row * $height), max(-180.0, $cell->column * $width)),
            Coordinates::of(min(90.0, ($cell->row + 1) * $height), min(180.0, ($cell->column + 1) * $width)),
        ];
    }

    /**
     * The middle of a cell, which stands for every position in it.
     */
    public function center(GridCell $cell): Coordinates
    {
        [$southWest, $northEast] = $this->bounds($cell);

        return Coordinates::of(($southWest->latitude + $northEast->latitude) / 2, ($southWest->longitude + $northEast->longitude) / 2);
    }

    private function latitudeStep(): float
    {
        return $this->metres / self::METRES_PER_DEGREE;
    }

    private function longitudeStep(int $row): float
    {
        $middle = ($row + 0.5) * $this->latitudeStep();

        return $this->metres / (self::METRES_PER_DEGREE * max(self::MIN_COSINE, cos(deg2rad(min(90.0, max(-90.0, $middle))))));
    }
}
