<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Override;
use Phil\LtiBundle\Infrastructure\Dbal\DbalAccessTokens;
use Phil\LtiBundle\Infrastructure\Dbal\DbalKeyStore;
use Phil\LtiBundle\Infrastructure\Dbal\DbalLineItemStore;
use Phil\LtiBundle\Infrastructure\Dbal\DbalLoginStates;
use Phil\LtiBundle\Infrastructure\Dbal\DbalNonces;
use Phil\LtiBundle\Infrastructure\Dbal\DbalPendingScores;
use Phil\LtiBundle\Infrastructure\Dbal\DbalScoreStore;
use Phil\LtiBundle\Infrastructure\Dbal\LtiTables;
use Phil\LtiBundle\Infrastructure\Dbal\Row;
use Phil\LtiBundle\Tests\Unit\Fixture\Stores;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DbalNonces::class)]
#[CoversClass(DbalLoginStates::class)]
#[CoversClass(DbalAccessTokens::class)]
#[CoversClass(DbalLineItemStore::class)]
#[CoversClass(DbalScoreStore::class)]
#[CoversClass(DbalPendingScores::class)]
#[CoversClass(DbalKeyStore::class)]
#[CoversClass(LtiTables::class)]
#[CoversClass(Row::class)]
final class SqliteStoresTest extends StoresTestCase
{
    private ?Connection $connection = null;

    #[Override]
    protected function connection(): Connection
    {
        return $this->connection ??= Stores::sqlite($this->tables);
    }
}
