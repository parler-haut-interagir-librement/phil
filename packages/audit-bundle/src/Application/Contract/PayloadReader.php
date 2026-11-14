<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Application\Contract;

use Phil\AuditBundle\Domain\Chain\AuditRow;

/**
 * Reads back the fields of a payload, for a person to read: the inverse of the application's `CanonicalSerializer`,
 * version by version. Verification never uses it; it hashes the payload as stored.
 */
interface PayloadReader
{
    /**
     * @return array<string, scalar|null>|null the fields, by name; `null` for a version it does not read
     */
    public function fields(AuditRow $row): ?array;
}
