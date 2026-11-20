<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Application\Fixture;

use Phil\TenancyBundle\Application\Contract\TenantActivation;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use RuntimeException;

/**
 * An activation that fails on the tenants it is given, and on leaving when asked to.
 */
final class FailingActivation implements TenantActivation
{
    /** @var list<string> the tenants activated, and `-` for each deactivation */
    public array $calls = [];

    /**
     * @param list<string> $failingTenants
     */
    public function __construct(
        private readonly array $failingTenants = [],
        private readonly bool $failsOnLeaving = false,
        private readonly string $leaveMessage = 'cannot leave',
    ) {
    }

    public function activate(TenantId $tenant): void
    {
        $this->calls[] = $tenant->value;

        if (\in_array($tenant->value, $this->failingTenants, true)) {
            throw new RuntimeException('cannot enter '.$tenant->value);
        }
    }

    public function deactivate(): void
    {
        $this->calls[] = '-';

        if ($this->failsOnLeaving) {
            throw new RuntimeException($this->leaveMessage);
        }
    }
}
