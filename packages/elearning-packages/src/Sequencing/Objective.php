<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

/**
 * A learning objective of an activity: its identifier — none for a primary objective that names none —, whether it is
 * the primary objective, which stands for the activity in the rollup, whether its measure decides its satisfaction and
 * from which measure, and its maps to the global objectives.
 */
final readonly class Objective
{
    public const string PRIMARY = '#primary';

    /**
     * @param list<ObjectiveMap> $maps
     */
    public function __construct(
        public ?string $id = null,
        public bool $primary = false,
        public bool $satisfiedByMeasure = false,
        public float $minNormalizedMeasure = 1.0,
        public array $maps = [],
    ) {
    }

    /**
     * The key of the objective in the state of a session: its identifier, or `#primary` for a primary objective
     * without one.
     */
    public function key(): string
    {
        return $this->id ?? self::PRIMARY;
    }
}
