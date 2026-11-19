<?php

declare(strict_types=1);

namespace Phil\Assignment;

/**
 * An applicant left without a slot, with what became of each choice, in their order.
 */
final readonly class Unplaced
{
    /**
     * @param list<RefusedChoice> $choices the most wanted first; empty for an applicant who ranked nothing
     */
    public function __construct(
        public string $applicant,
        public array $choices,
    ) {
    }
}
