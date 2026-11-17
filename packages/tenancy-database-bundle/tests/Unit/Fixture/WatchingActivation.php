<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Fixture;

use Doctrine\DBAL\Connection;
use Override;
use Phil\TenancyBundle\Application\Contract\TenantActivation;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;

/**
 * Records each time the tenant is left, with the transaction level the connection had then.
 */
final class WatchingActivation implements TenantActivation
{
    /** @var list<int> */
    public array $left = [];

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    #[Override]
    public function activate(TenantId $tenant): void
    {
    }

    #[Override]
    public function deactivate(): void
    {
        $this->left[] = $this->connection->getTransactionNestingLevel();
    }
}
