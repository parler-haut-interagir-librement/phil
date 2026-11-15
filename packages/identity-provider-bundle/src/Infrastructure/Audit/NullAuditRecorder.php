<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Audit;

use Override;
use Phil\IdentityProviderBundle\Application\Contract\AuditRecorder;

/**
 * No audit trail: the events are still dispatched.
 */
final readonly class NullAuditRecorder implements AuditRecorder
{
    #[Override]
    public function record(string $action, ?string $accountId, array $fields = []): void
    {
    }
}
