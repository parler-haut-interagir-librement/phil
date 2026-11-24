<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Domain\Contract;

use DateTimeImmutable;

/**
 * The vote tokens already used, each for one question, until they expire.
 */
interface SpentVoteTokenRepository
{
    /**
     * Marks a token as used for a question, in one statement that two votes at once cannot both pass.
     *
     * @param string $id the hash of the token's random part and the question
     *
     * @return bool whether it was not used yet
     */
    public function spend(string $id, DateTimeImmutable $expiresAt): bool;

    /**
     * Forgets the tokens expired before this moment, which can no longer vote anyway.
     *
     * @return int how many were forgotten
     */
    public function purge(DateTimeImmutable $now): int;
}
