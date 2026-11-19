<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Application\Contract;

use Phil\UserSecretsBundle\Application\Audit\SecretEvent;

/**
 * An audit hook: receives every event of every secret — stored, read, refreshed, revoked, removed, erased,
 * re-sealed. The application writes them to its audit trail, its logs, or a notification to the owner.
 *
 * Called after the change is saved; an auditor that throws lets the exception through to the caller.
 */
interface SecretAuditor
{
    public function record(SecretEvent $event): void;
}
