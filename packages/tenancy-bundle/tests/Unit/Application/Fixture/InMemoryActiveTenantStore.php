<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Application\Fixture;

use Phil\TenancyBundle\Application\Contract\ActiveTenantStore;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;

final class InMemoryActiveTenantStore implements ActiveTenantStore
{
    public int $forgotten = 0;

    public function __construct(
        private ?TenantId $tenant = null,
    ) {
    }

    public function remembered(): ?TenantId
    {
        return $this->tenant;
    }

    public function remember(TenantId $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function forget(): void
    {
        $this->tenant = null;
        ++$this->forgotten;
    }
}
