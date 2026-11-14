<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Phil\PrivacyBundle\Application\Exception\AnonymizationRefused;
use Phil\PrivacyBundle\Infrastructure\Database\DbalAnonymizationTarget;
use Phil\PrivacyBundle\Tests\Unit\Infrastructure\Database\Fixture\Files;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The anonymization target on a real SQLite file: DBAL reads and writes the rows.
 */
#[CoversClass(DbalAnonymizationTarget::class)]
final class DbalAnonymizationTargetTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = Files::directory();
    }

    protected function tearDown(): void
    {
        Files::remove($this->directory);
    }

    /**
     * Rows are read page by page in key order, keyed by the names as asked, and written by key.
     */
    public function testTheTargetReadsInPagesAndWritesByKey(): void
    {
        $connection = $this->database($this->directory.'/app.sqlite');
        $target = new DbalAnonymizationTarget($connection, 2);

        self::assertSame(['id'], $target->keyOf('member'));
        self::assertSame(['member_id', 'role'], $target->keyOf('grants'));
        self::assertSame([
            ['id' => 1, 'Email' => 'ada@example.com', 'order' => 'first'],
            ['id' => 2, 'Email' => 'bob@example.com', 'order' => 'second'],
            ['id' => 3, 'Email' => null, 'order' => 'third'],
        ], iterator_to_array($target->rows('member', ['id'], ['Email', 'order']), false));
        self::assertSame([['id' => 1], ['id' => 2], ['id' => 3]], iterator_to_array(new DbalAnonymizationTarget($connection, 1)->rows('main.member', ['id'], []), false));
        self::assertCount(3, iterator_to_array(new DbalAnonymizationTarget($connection, 3)->rows('member', ['id'], []), false));
        self::assertSame(
            [['member_id' => 1, 'role' => 'admin', 'note' => 'all'], ['member_id' => 1, 'role' => 'reader', 'note' => 'kept']],
            iterator_to_array($target->rows('grants', ['member_id', 'role'], ['note']), false),
        );
        self::assertSame(['order'], $target->keyOf('keyword'));
        self::assertSame([['order' => 'a', 'label' => 'first'], ['order' => 'b', 'label' => 'second']], iterator_to_array($target->rows('keyword', ['order'], ['label']), false));

        $result = $target->transactional(static function () use ($target): string {
            $target->update('member', ['id' => 2], ['email' => 'x@example.com', 'order' => 'last']);
            $target->update('main.grants', ['member_id' => 1, 'role' => 'admin'], ['note' => 'none']);

            return 'done';
        });

        self::assertSame('done', $result);
        self::assertSame(['email' => 'x@example.com', 'order' => 'last'], $connection->fetchAssociative('SELECT email, "order" FROM member WHERE id = 2'));
        self::assertSame(['none', 'kept'], $connection->fetchFirstColumn('SELECT note FROM grants ORDER BY role'));

        try {
            $target->transactional(static function () use ($target): never {
                $target->update('member', ['id' => 1], ['email' => 'y@example.com']);

                throw new RuntimeException('stop');
            });
        } catch (RuntimeException) {
        }

        self::assertSame('ada@example.com', $connection->fetchOne('SELECT email FROM member WHERE id = 1'), 'A failure rolls the work back.');

        foreach (['nokey', 'ghost'] as $table) {
            try {
                $target->keyOf($table);
                self::fail($table);
            } catch (AnonymizationRefused $anonymizationRefused) {
                self::assertSame(\sprintf('The table "%s" has no primary key, or does not exist: its rows cannot be rewritten one by one.', $table), $anonymizationRefused->getMessage());
            }
        }
    }

    private function database(string $path): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]);
        $connection->executeStatement('CREATE TABLE member (id INTEGER PRIMARY KEY, email VARCHAR(255) NULL, "order" VARCHAR(20) NOT NULL)');
        $connection->executeStatement('CREATE TABLE grants (member_id INTEGER NOT NULL, role VARCHAR(20) NOT NULL, note VARCHAR(20) NOT NULL, PRIMARY KEY (member_id, role))');
        $connection->executeStatement('CREATE TABLE nokey (name VARCHAR(20))');
        $connection->executeStatement('CREATE TABLE keyword ("order" VARCHAR(5) PRIMARY KEY, label VARCHAR(20) NOT NULL)');
        $connection->executeStatement("INSERT INTO keyword VALUES ('b', 'second'), ('a', 'first')");
        $connection->executeStatement("INSERT INTO member (id, email, \"order\") VALUES (2, 'bob@example.com', 'second'), (1, 'ada@example.com', 'first'), (3, NULL, 'third')");
        $connection->executeStatement("INSERT INTO grants VALUES (1, 'admin', 'all'), (1, 'reader', 'kept')");

        return $connection;
    }
}
