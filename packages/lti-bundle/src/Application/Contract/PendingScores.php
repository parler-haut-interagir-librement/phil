<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Application\Contract;

use Phil\LtiBundle\Application\PendingScore;

/**
 * The scores the tool still has to send, kept until the platform takes them: the message of the bus carries only their
 * identifier.
 */
interface PendingScores
{
    public function save(PendingScore $score): void;

    public function find(string $id): ?PendingScore;

    public function delete(string $id): void;
}
