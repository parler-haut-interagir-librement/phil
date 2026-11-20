<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

/**
 * What a rollup rule sets on its activity when it holds.
 */
enum RollupAction: string
{
    case Satisfied = 'satisfied';
    case NotSatisfied = 'notSatisfied';
    case Completed = 'completed';
    case Incomplete = 'incomplete';

    public function isObjective(): bool
    {
        return self::Satisfied === $this || self::NotSatisfied === $this;
    }
}
