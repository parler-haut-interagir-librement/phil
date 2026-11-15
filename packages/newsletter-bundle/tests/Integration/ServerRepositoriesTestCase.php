<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
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
