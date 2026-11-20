<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

/**
 * What is known of an objective: whether it is satisfied, and its measure from -1 to 1; null when unknown.
 */
final readonly class ObjectiveStatus
{
    public function __construct(
        public ?bool $satisfied = null,
        public ?float $measure = null,
    ) {
    }
}
