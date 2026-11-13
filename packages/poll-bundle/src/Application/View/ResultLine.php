<?php

declare(strict_types=1);

namespace Phil\PollBundle\Application\View;

/**
 * One option in the results shown: its place, its label, its score as the method counts it and as it is written,
 * whether it won or was elected, and the length of its bar beside the highest score.
 */
final readonly class ResultLine
{
    public function __construct(
        public string $option,
        public string $label,
        public int $place,
        public int $score,
        public string $display,
        public int $max,
        public bool $winner,
    ) {
    }
}
