<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Override;
use Phil\LtiBundle\Infrastructure\Console\SchemaCommand;
use Phil\LtiBundle\Infrastructure\Dbal\DbalAccessTokens;
use Phil\LtiBundle\Infrastructure\Dbal\DbalDeepLinkingReturns;
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
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

#[CoversClass(DbalNonces::class)]
#[CoversClass(DbalLoginStates::class)]
#[CoversClass(DbalAccessTokens::class)]
#[CoversClass(DbalLineItemStore::class)]
#[CoversClass(DbalScoreStore::class)]
#[CoversClass(DbalPendingScores::class)]
#[CoversClass(DbalDeepLinkingReturns::class)]
#[CoversClass(SchemaCommand::class)]
#[CoversClass(DbalKeyStore::class)]
#[CoversClass(LtiTables::class)]
#[CoversClass(Row::class)]
final class SqliteStoresTest extends StoresTestCase
{
    private ?Connection $connection = null;

    #[Override]
    protected function connection(): Connection
    {
        return $this->connection ??= Stores::sqlite($this->tables, $this->interleaving);
    }

    public function testTheNamesOfTheColumnsAndIndexesAreComparedWhateverTheirCase(): void
    {
        $connection = $this->connection();
        $nonces = $this->tables->nonces;
        $connection->executeStatement('DROP TABLE '.$nonces);
        $connection->executeStatement('CREATE TABLE '.$nonces.' ("SCOPE" VARCHAR(64) NOT NULL, "VALUE_HASH" VARCHAR(64) NOT NULL, "EXPIRES_AT" BIGINT NOT NULL, PRIMARY KEY ("SCOPE", "VALUE_HASH"))');
        $connection->executeStatement('CREATE INDEX "'.strtoupper($nonces).'_EXPIRY" ON '.$nonces.' ("EXPIRES_AT")');

        $output = new BufferedOutput();
        new SchemaCommand($connection, $this->tables)(new SymfonyStyle(new ArrayInput([]), $output));

        self::assertSame(' [OK] The LTI tables are up to date. ', (string) preg_replace('/\s+/', ' ', $output->fetch()));
    }
}
