<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Application;

use DateTimeImmutable;

/**
 * What a valid vote token holds: its random part and the moment it expires.
 */
final readonly class VoteTicket
{
    public function __construct(
        public string $nonce,
        public DateTimeImmutable $expiresAt,
    ) {
    }

    /**
     * The identifier of this token's vote for one question, under which it is kept as used: a hash, which nothing
     * links to a person.
     */
    public function spentId(string $questionId): string
    {
        return hash('sha256', $this->nonce."\0".$questionId);
    }
}
