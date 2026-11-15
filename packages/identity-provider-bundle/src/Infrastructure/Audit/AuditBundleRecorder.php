<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Audit;

use Override;
use Phil\AuditBundle\Application\AuditTrail;
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\IdentityProviderBundle\Application\Contract\AuditRecorder;

/**
 * The events in the hash-chained trail of `phil/audit-bundle`, in the segment `identity`: the action, the account as
 * the actor, and the identifiers of the event as the payload.
 */
final readonly class AuditBundleRecorder implements AuditRecorder
{
    public const string SEGMENT = 'identity';

    public function __construct(
        private AuditTrail $trail,
        private string $segment = self::SEGMENT,
    ) {
    }

    #[Override]
    public function record(string $action, ?string $accountId, array $fields = []): void
    {
        $this->trail->record($this->segment, ['action' => $action] + $fields, Facts::of($action, $accountId));
    }
}
