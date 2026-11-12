<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\FormsAutosave\Application\Contract\DraftStore;
use Phil\FormsAutosave\Domain\Exception\InvalidDraft;
use Phil\FormsAutosave\Domain\Model\Draft;
use Phil\FormsAutosave\Domain\ValueObject\DraftKey;
use Phil\FormsAutosave\Infrastructure\Doctrine\DoctrineDraftStore;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The store on SQLite in memory, its table created from the mapping as an application's schema tools create it.
 */
#[CoversClass(DoctrineDraftStore::class)]
final class DoctrineDraftStoreTest extends DraftStoreTestCase
{
    #[Override]
    protected function store(): DraftStore
    {
        return new DoctrineDraftStore($this->connection());
    }

    public function testTimesAreStoredInUtc(): void
    {
        $connection = $this->connection();
        new DoctrineDraftStore($connection)->insert(Draft::first(self::ADA, DraftKey::of('abcdefghijklmnop'), 'x', new DateTimeImmutable('2026-11-08 10:00:00 America/Montreal'), 60));

        self::assertSame(['saved_at' => '2026-11-08 15:00:00', 'expires_at' => '2026-11-08 15:01:00'], $connection->fetchAssociative('SELECT saved_at, expires_at FROM '.Draft::TABLE));
    }

    public function testARowThatIsNoDraftIsRefused(): void
    {
        // A table without constraints, as a hand-written migration could leave it, to read what the mapping forbids.
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE '.Draft::TABLE.' (owner TEXT, draft_key TEXT, revision, payload, saved_at, expires_at)');

        $store = new DoctrineDraftStore($connection);
        $key = DraftKey::of('abcdefghijklmnop');

        foreach ([
            ['revision' => 'x', 'message' => 'The revision of a stored draft is a number.'],
            ['revision' => null, 'message' => 'The revision of a stored draft is a number.'],
            ['payload' => null, 'message' => 'The column "payload" of a stored draft is a text.'],
            ['saved_at' => null, 'message' => 'The column "saved_at" of a stored draft is a text.'],
            ['saved_at' => '2026-11-08T15:00:00', 'message' => 'The column "saved_at" of a stored draft is a time.'],
            ['expires_at' => 'tomorrow', 'message' => 'The column "expires_at" of a stored draft is a time.'],
            ['revision' => 0, 'message' => 'The revision of a draft is positive.'],
        ] as $row) {
            $message = $row['message'];
            unset($row['message']);
            $connection->executeStatement('DELETE FROM '.Draft::TABLE);
            $connection->insert(Draft::TABLE, [...['owner' => self::ADA, 'draft_key' => $key->value, 'revision' => 1, 'payload' => 'x', 'saved_at' => '2026-11-08 15:00:00', 'expires_at' => '2026-11-08 15:01:00'], ...$row]);

            try {
                $store->find(self::ADA, $key);
                self::fail($message);
            } catch (InvalidDraft $invalid) {
                self::assertSame($message, $invalid->getMessage());
            }
        }
    }

    public function testTheTableIsMappedWithItsColumns(): void
    {
        $columns = $this->connection()->fetchFirstColumn('SELECT name FROM pragma_table_info(?)', [Draft::TABLE]);

        self::assertSame(['owner', 'draft_key', 'revision', 'payload', 'saved_at', 'expires_at'], $columns);
    }

    private function connection(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $config->enableNativeLazyObjects(true);

        $entityManager = new EntityManager($connection, $config);
        new SchemaTool($entityManager)->createSchema([$entityManager->getClassMetadata(Draft::class)]);

        return $connection;
    }
}
