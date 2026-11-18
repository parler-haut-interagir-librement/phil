<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\OnboardingBundle\Infrastructure\Doctrine\BinaryKeysListener;
use PHPUnit\Framework\TestCase;

/**
 * The tables the ORM generates on a real server read from the environment: the progress of `Ann`, `ann`, `ANN` and `ánn` in one flow is four rows.
 */
abstract class ServerBinaryKeysTestCase extends TestCase
{
    private ?Connection $connection = null;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    public function testKeysThatDifferByCaseOrAccentAreKeptApart(): void
    {
        $connection = $this->connection();

        foreach ([
            ['flow' => 'welcome', 'started_at' => '2026-11-17 10:00:00', 'updated_at' => '2026-11-17 10:00:00', 'steps' => '[]', 'user_identifier' => 'Ann'],
            ['flow' => 'welcome', 'started_at' => '2026-11-17 10:00:00', 'updated_at' => '2026-11-17 10:00:00', 'steps' => '[]', 'user_identifier' => 'ann'],
            ['flow' => 'welcome', 'started_at' => '2026-11-17 10:00:00', 'updated_at' => '2026-11-17 10:00:00', 'steps' => '[]', 'user_identifier' => 'ANN'],
            ['flow' => 'welcome', 'started_at' => '2026-11-17 10:00:00', 'updated_at' => '2026-11-17 10:00:00', 'steps' => '[]', 'user_identifier' => 'ánn'],
        ] as $row) {
            $connection->insert('phil_onboarding_progress', $row);
        }

        foreach (['Ann', 'ann', 'ANN', 'ánn'] as $value) {
            self::assertSame([$value], $connection->fetchFirstColumn('SELECT user_identifier FROM phil_onboarding_progress WHERE user_identifier = ?', [$value]), $value);
        }
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->connection?->close();
        $this->connection = null;
    }

    /**
     * The bundle's tables, created afresh from the ORM's schema: only those, since the server is shared with the tests of
     * other packages.
     */
    protected function connection(): Connection
    {
        if ($this->connection instanceof Connection) {
            return $this->connection;
        }

        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        $connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
        $this->connection = $connection;
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $config->enableNativeLazyObjects(true);

        $entityManager = new EntityManager($connection, $config);
        $entityManager->getEventManager()->addEventListener(Events::loadClassMetadata, new BinaryKeysListener());
        $schema = new SchemaTool($entityManager)->getSchemaFromMetadata(array_map($entityManager->getClassMetadata(...), array_keys(BinaryKeysListener::FIELDS)));
        $manager = $connection->createSchemaManager();

        foreach ($schema->getTables() as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table->getObjectName()->toString());
        }

        foreach ($schema->getTables() as $table) {
            $manager->createTable($table);
        }

        return $connection;
    }
}
