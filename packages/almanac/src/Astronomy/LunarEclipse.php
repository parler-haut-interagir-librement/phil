<?php

declare(strict_types=1);

namespace Phil\Almanac\Astronomy;

use DateTimeImmutable;

/**
 * A lunar eclipse at its greatest, and its phases, in UTC.
 *
 * `gamma` is the least distance of the Moon's centre from the axis of the Earth's shadow, in Earth radii. A
 * magnitude is the fraction of the Moon's diameter in the penumbra or the umbra at the greatest, over 1 when it is
 * wholly in it. Each phase is a span around the greatest: the penumbral one always, the partial one when the Moon
 * enters the umbra, the total one when it is wholly in it.
 */
final readonly class LunarEclipse
{
    /**
     * @param array{DateTimeImmutable, DateTimeImmutable}      $penumbral
     * @param array{DateTimeImmutable, DateTimeImmutable}|null $partial
     * @param array{DateTimeImmutable, DateTimeImmutable}|null $total
     */
    public function __construct(
        public DateTimeImmutable $at,
        public LunarEclipseKind $kind,
        public float $gamma,
        public float $penumbralMagnitude,
        public float $umbralMagnitude,
        public array $penumbral,
        public ?array $partial,
        public ?array $total,
    ) {
    }
}
