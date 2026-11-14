<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Application\Fixture;

use Phil\TenancyBundle\Application\Contract\TenantActivation;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;

final class RecordingActivation implements TenantActivation
{
    /** @var list<string> the tenants activated, and `-` for each deactivation */
    public array $calls = [];

    public function activate(TenantId $tenant): void
    {
        $this->calls[] = $tenant->value;
    }

    public function deactivate(): void
    {
        $this->calls[] = '-';
    }
}
