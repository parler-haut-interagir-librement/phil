<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Contract;

/**
 * Writes what happened to an account to an audit trail: an action and identifiers, never an address, a name, a code
 * or a secret. The bundle writes through `phil/audit-bundle` when it is installed, and nowhere otherwise.
 */
interface AuditRecorder
{
    /**
     * @param array<string, bool|int|string|null> $fields identifiers and flags only
     */
    public function record(string $action, ?string $accountId, array $fields = []): void;
}
