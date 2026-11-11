<?php

declare(strict_types=1);

namespace Phil\SvgChart;

/**
 * One series of a chart: its name in the legend, a value for each label — `null` where it has none —, and its colour,
 * `#rrggbb`, or the palette's when null. A pie or a doughnut takes one colour per label from `$colours`.
 */
final readonly class Dataset
{
    /**
     * @param list<float|null> $values
     * @param list<string>     $colours one colour per label, for a pie or a doughnut
     */
    public function __construct(
        public string $label,
        public array $values,
        public ?string $colour = null,
        public array $colours = [],
    ) {
    }
}
