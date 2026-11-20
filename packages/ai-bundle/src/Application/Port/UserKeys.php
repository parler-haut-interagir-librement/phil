<?php

declare(strict_types=1);

namespace Phil\AiBundle\Application\Port;

/**
 * The keys people lend the application for a platform; `phil/user-secrets-bundle` keeps them.
 */
interface UserKeys
{
    /**
     * @param string $purpose what the key is read for, written to the audit trail of the secrets
     *
     * @return string|null the person's key, or null when they have none for the platform, or revoked it
     */
    public function keyFor(string $owner, string $platform, string $purpose): ?string;
}
