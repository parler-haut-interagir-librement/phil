<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Migration;

use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;

/**
 * One database to migrate, and the tenant whose `--tenant` option moves the connection there: none for the shared
 * database.
 */
final readonly class MigrationTarget
{
    public function __construct(
        public Placement $database,
        public ?TenantId $tenant = null,
    ) {
    }

    public static function shared(): self
    {
        return new self(Placement::shared());
    }
}
