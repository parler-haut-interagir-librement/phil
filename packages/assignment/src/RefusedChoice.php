<?php

declare(strict_types=1);

namespace Phil\Assignment;

/**
 * A ranked choice that did not become the applicant's placement, and why.
 */
final readonly class RefusedChoice
{
    public function __construct(
        public string $slot,
        public ChoiceOutcome $outcome,
    ) {
    }
}
