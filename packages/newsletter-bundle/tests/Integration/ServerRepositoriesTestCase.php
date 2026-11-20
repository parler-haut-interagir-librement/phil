<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Integration;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\TableEditor;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\NewsletterBundle\Domain\Enum\DeliveryStatus;
use Phil\NewsletterBundle\Domain\Model\Delivery;
use Phil\NewsletterBundle\Domain\Model\SendRun;
use Phil\NewsletterBundle\Infrastructure\Dbal\NewsletterSchema;
use Phil\NewsletterBundle\Tests\Unit\Fixture\Newsletter;
use Phil\NewsletterBundle\Tests\Unit\Infrastructure\RepositoriesTestCase;

/**
 * Every promise of the repositories on a real server read from the environment: the inserts that leave a row alone in
 * that server's dialect, the upserts, the counts added in one statement.
 */
abstract class ServerRepositoriesTestCase extends RepositoriesTestCase
{
    private ?Connection $connection = null;

    abstract protected function variable(): string;

    #[Override]
    protected function tearDown(): void
    {
        $this->connection?->close();
    }

    public function testTwoConnectionsCountEveryClick(): void
    {
        $other = new Newsletter($this->connect(false));
        $this->newsletter->engagement->countClick('c1', 0);
        $other->engagement->countClick('c1', 0);
        $other->connection->close();

        self::assertSame([0 => 2], $this->newsletter->engagement->clicks('c1'));
    }

    public function testAnInstallOfTheVersionBeforeIsMigratedWithItsRowsKept(): void
    {
        $connection = $this->newsletter->connection;

        foreach (NewsletterSchema::TABLES as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }

        $manager = $connection->createSchemaManager();
        $before = NewsletterSchema::configure($manager->introspectSchema(), $connection->getDatabasePlatform())->edit()
            ->dropTableByUnquotedName(NewsletterSchema::SEND_RUNS)
            ->modifyTableByUnquotedName(NewsletterSchema::CAMPAIGNS, static function (TableEditor $table): void {
                $table->dropColumnByUnquotedName('author')->dropColumnByUnquotedName('approved_by')->dropColumnByUnquotedName('approved_at');
            })
            ->modifyTableByUnquotedName(NewsletterSchema::DELIVERIES, static function (TableEditor $table): void {
                $table->dropIndexByUnquotedName(NewsletterSchema::DELIVERIES.'_i2')->dropColumnByUnquotedName('run_id');
            })
            ->create();
        $manager->migrateSchema($before);
        $connection->insert(NewsletterSchema::CAMPAIGNS, ['id' => 'c1', 'site' => 'default', 'list_id' => 'l1', 'subject' => 'S', 'body' => 'B', 'preheader' => '', 'click_tracking' => 'aggregate', 'status' => 'sent']);
        $connection->insert(NewsletterSchema::DELIVERIES, ['id' => 'd1', 'subscriber_id' => 's1', 'campaign_id' => 'c1', 'status' => 'sent', 'attempts' => 1, 'created_at' => 1]);

        $manager->migrateSchema(NewsletterSchema::configure($manager->introspectSchema(), $connection->getDatabasePlatform()));

        $migrated = new Newsletter($connection, false);
        self::assertSame([null, null], [$migrated->campaigns->get('c1')?->author, $migrated->campaigns->get('c1')?->approval]);
        $delivery = $migrated->deliveries->get('d1');
        self::assertInstanceOf(Delivery::class, $delivery);
        self::assertSame([DeliveryStatus::Sent, null], [$delivery->status, $delivery->runId]);
        self::assertTrue($migrated->runs->create(new SendRun(SendRun::idFor('c1', 1), 'c1', 1, new DateTimeImmutable('@1800000000'))));
        self::assertTrue($manager->introspectSchema()->getTable(NewsletterSchema::DELIVERIES)->hasIndex(NewsletterSchema::DELIVERIES.'_i2'));
        $after = $manager->introspectSchema();
        self::assertSame([], $connection->getDatabasePlatform()->getAlterSchemaSQL($manager->createComparator()->compareSchemas($after, NewsletterSchema::configure($after, $connection->getDatabasePlatform()))));
    }

    #[Override]
    protected function newsletter(): Newsletter
    {
        $connection = $this->connection ??= $this->connect(true);

        return new Newsletter($connection);
    }

    private function connect(bool $reset): Connection
    {
        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        $connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));

        if ($reset) {
            foreach (NewsletterSchema::TABLES as $table) {
                $connection->executeStatement('DROP TABLE IF EXISTS '.$table);
            }
        }

        return $connection;
    }
}
