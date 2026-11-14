<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Unit\Fixture;

use Override;
use Phil\AuditBundle\Application\Contract\TenantScope;

/**
 * A tenant scope a test sets.
 */
final class FixedTenant implements TenantScope
{
    public function __construct(
        public ?string $tenant = null,
    ) {
    }

    #[Override]
    public function current(): ?string
    {
        return $this->tenant;
    }
}
