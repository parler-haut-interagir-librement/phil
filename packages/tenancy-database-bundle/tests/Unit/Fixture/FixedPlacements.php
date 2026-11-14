<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Fixture;

use Override;
use Phil\TenancyBundle\Application\Contract\TenantPlacement;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;

/**
 * A placement that answers from a map of database names, and counts its questions; read-only.
 */
final class FixedPlacements implements TenantPlacement
{
    public int $asked = 0;

    /**
     * @param array<string, string> $dedicated
     */
    public function __construct(
        private readonly array $dedicated = [],
    ) {
    }

    #[Override]
    public function placementOf(TenantId $tenant): Placement
    {
        ++$this->asked;
        $database = $this->dedicated[$tenant->value] ?? null;

        return null === $database ? Placement::shared() : Placement::dedicated($database);
    }
}
