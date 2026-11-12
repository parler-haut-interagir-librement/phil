<?php

declare(strict_types=1);

namespace Phil\Almanac\Astronomy;

use DateTimeImmutable;

/**
 * A solar eclipse at its greatest, in UTC.
 *
 * `gamma` is the least distance of the axis of the Moon's shadow from the Earth's centre, in Earth radii, negative
 * south of it: an eclipse whose axis touches the Earth, `|gamma|` under 0.9972, is central. `magnitude` is the
 * fraction of the Sun's diameter covered at the greatest of a partial eclipse; the method does not give it for a
 * central one, where it is null.
 */
final readonly class SolarEclipse
{
    public function __construct(
        public DateTimeImmutable $at,
        public SolarEclipseKind $kind,
        public bool $central,
        public float $gamma,
        public ?float $magnitude,
    ) {
    }
}
