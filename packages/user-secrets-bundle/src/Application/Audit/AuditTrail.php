<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Application\Audit;

use Phil\UserSecretsBundle\Application\Contract\SecretAuditor;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;
use Psr\Clock\ClockInterface;

/**
 * Hands each event to every auditor the application registered, dated by the clock.
 */
final readonly class AuditTrail
{
    /**
     * @param iterable<SecretAuditor> $auditors
     */
    public function __construct(
        private ClockInterface $clock,
        private iterable $auditors = [],
    ) {
    }

    public function record(SecretEventKind $kind, Owner $owner, SecretName $name, ?string $purpose = null): void
    {
        $event = new SecretEvent($kind, $owner, $name, $this->clock->now(), $purpose);

        foreach ($this->auditors as $auditor) {
            $auditor->record($event);
        }
    }
}
