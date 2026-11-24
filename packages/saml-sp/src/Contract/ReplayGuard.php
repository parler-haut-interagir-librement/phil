<?php

declare(strict_types=1);

namespace Phil\SamlSp\Contract;

use DateTimeImmutable;

/**
 * Remembers the assertions accepted until they expire, so that one posted again is refused. A guard shared by every
 * worker and every server of the application stops a replay on any of them.
 */
interface ReplayGuard
{
    /**
     * Whether this is the first use of the assertion of this issuer; it is remembered until `$until`.
     */
    public function firstUse(string $issuer, string $assertionId, DateTimeImmutable $until): bool;
}
