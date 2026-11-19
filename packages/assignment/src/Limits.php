<?php

declare(strict_types=1);

namespace Phil\Assignment;

use Phil\Assignment\Exception\InvalidProblem;

/**
 * The sizes a problem may reach, checked before any work: a problem past them is refused, so that input nobody
 * bounded cannot hold a worker. The defaults fit a large convention; an application lowers them for input it does not
 * trust, or raises them for an offline run.
 */
final readonly class Limits
{
    /**
     * @throws InvalidProblem
     */
    public function __construct(
        public int $slots = 5_000,
        public int $applicants = 20_000,
        public int $choices = 20,
        public int $members = 50,
    ) {
        if ($slots < 1 || $applicants < 1 || $choices < 1 || $members < 1) {
            throw InvalidProblem::because('Every limit of a problem is at least 1.');
        }
    }
}
