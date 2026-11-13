<?php

declare(strict_types=1);

namespace Phil\PollBundle\Application\View;

/**
 * One option on the form of a ballot: its identifier, its label, and what the viewer's ballot gave it — chosen, and
 * the rank on a ranked ballot.
 */
final readonly class BallotChoice
{
    public function __construct(
        public string $id,
        public string $label,
        public bool $chosen = false,
        public ?int $rank = null,
    ) {
    }
}
