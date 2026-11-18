<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\NewsletterBundle\Infrastructure\Dbal\NewsletterSchema;
use PHPUnit\Framework\TestCase;

/**
 * The tables on a real server read from the environment: the address keys `muller@example.com`, `müller@example.com` and `mullér@example.com` of one site are three subscribers.
 */
abstract class ServerNewsletterSchemaCollationTestCase extends TestCase
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
            ['id' => bin2hex(random_bytes(18)), 'site' => 'acme', 'email' => 'someone@example.com', 'status' => 'active', 'created_at' => 1, 'soft_bounces' => 0, 'email_key' => 'muller@example.com'],
            ['id' => bin2hex(random_bytes(18)), 'site' => 'acme', 'email' => 'someone@example.com', 'status' => 'active', 'created_at' => 1, 'soft_bounces' => 0, 'email_key' => 'müller@example.com'],
            ['id' => bin2hex(random_bytes(18)), 'site' => 'acme', 'email' => 'someone@example.com', 'status' => 'active', 'created_at' => 1, 'soft_bounces' => 0, 'email_key' => 'mullér@example.com'],
        ] as $row) {
            $connection->insert(NewsletterSchema::SUBSCRIBERS, $row);
        }

        foreach (['muller@example.com', 'müller@example.com', 'mullér@example.com'] as $value) {
            self::assertSame([$value], $connection->fetchFirstColumn('SELECT email_key FROM '.NewsletterSchema::SUBSCRIBERS.' WHERE email_key = ?', [$value]), $value);
        }
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->connection?->close();
        $this->connection = null;
    }

    /**
     * The package's tables, created afresh: only those, since the server is shared with the tests of other packages.
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
        $schema = NewsletterSchema::configure(Schema::editor()->create(), $connection->getDatabasePlatform());

        foreach ($schema->getTables() as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table->getObjectName()->toString());
        }

        $manager = $connection->createSchemaManager();

        foreach ($schema->getTables() as $table) {
            $manager->createTable($table);
        }

        return $connection;
    }
}
