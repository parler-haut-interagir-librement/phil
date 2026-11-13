<?php

declare(strict_types=1);

namespace Phil\PollBundle\Application;

/**
 * A ballot just cast: its identifier and, for an anonymous poll, the receipt its voter keeps to change it later. The
 * receipt is shown once and never kept: only its hash is.
 */
final readonly class CastVote
{
    public function __construct(
        public string $vote,
        public ?string $receipt,
    ) {
    }
}
