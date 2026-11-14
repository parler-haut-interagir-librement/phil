<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Domain\Resolution;

use Phil\TenancyBundle\Domain\Exception\NoActiveTenant;
use Phil\TenancyBundle\Domain\ValueObject\ReachableTenants;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;

/**
 * The result of resolving the active tenant.
 *
 * `$staleChoiceDropped` says that a remembered choice was no longer reachable and was ignored. The caller must
 * then forget it: a membership that is revoked and later granted again is a new decision, and the old choice
 * must not quietly come back with it.
 */
final readonly class Resolution
{
    private function __construct(
        public Outcome $outcome,
        private ?TenantId $tenant,
        public ReachableTenants $reachable,
        public bool $staleChoiceDropped,
    ) {
    }

    public static function active(TenantId $tenant, ReachableTenants $reachable, bool $staleChoiceDropped = false): self
    {
        return new self(Outcome::Active, $tenant, $reachable, $staleChoiceDropped);
    }

    public static function selectionRequired(ReachableTenants $reachable, bool $staleChoiceDropped = false): self
    {
        return new self(Outcome::SelectionRequired, null, $reachable, $staleChoiceDropped);
    }

    public static function noneReachable(bool $staleChoiceDropped = false): self
    {
        return new self(Outcome::NoneReachable, null, ReachableTenants::none(), $staleChoiceDropped);
    }

    public function isActive(): bool
    {
        return Outcome::Active === $this->outcome;
    }

    /**
     * @throws NoActiveTenant when no tenant is active
     */
    public function tenant(): TenantId
    {
        return $this->tenant ?? throw Outcome::SelectionRequired === $this->outcome ? NoActiveTenant::selectionRequired() : NoActiveTenant::noneReachable();
    }

    public function tenantOrNull(): ?TenantId
    {
        return $this->tenant;
    }
}
