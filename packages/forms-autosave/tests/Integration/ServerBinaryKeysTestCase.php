<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Tests\Integration;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\FormsAutosave\Domain\Model\Draft;
use Phil\FormsAutosave\Domain\ValueObject\DraftKey;
use Phil\FormsAutosave\Infrastructure\Doctrine\BinaryKeysListener;
use Phil\FormsAutosave\Infrastructure\Doctrine\DoctrineDraftStore;
use PHPUnit\Framework\TestCase;

/**
 * The draft table the ORM generates on a real server read from the environment: drafts whose keys differ only by case
 * are saved, read and deleted apart by the store, a key that differs by an accent is another row, and a draft saved
 * twice under the same key is still refused.
 */
abstract class ServerBinaryKeysTestCase extends TestCase
{
    /**
     * Draft keys that differ only by case, as `DraftKey` accepts them.
     */
    protected const array KEYS = ['abcdefghijklmnop', 'Abcdefghijklmnop', 'ABCDEFGHIJKLMNOP'];

    private const string OWNER = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private ?Connection $connection = null;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    public function testDraftsWhoseKeysDifferByCaseAreKeptApart(): void
    {
        $this->createTables($this->schema(true));
        $store = new DoctrineDraftStore($this->connection());
        $now = new DateTimeImmutable('2026-11-19 10:00:00 UTC');

        foreach (self::KEYS as $key) {
            self::assertTrue($store->insert(Draft::first(self::OWNER, DraftKey::of($key), $key, $now, 3600)), $key);
        }

        foreach (self::KEYS as $key) {
            self::assertSame($key, $store->find(self::OWNER, DraftKey::of($key))?->payload(), $key);
        }

        $store->delete(self::OWNER, DraftKey::of('abcdefghijklmnop'));

        self::assertNull($store->find(self::OWNER, DraftKey::of('abcdefghijklmnop')));
        self::assertSame('ABCDEFGHIJKLMNOP', $store->find(self::OWNER, DraftKey::of('ABCDEFGHIJKLMNOP'))?->payload());
        self::assertFalse($store->insert(Draft::first(self::OWNER, DraftKey::of('ABCDEFGHIJKLMNOP'), 'again', $now, 3600)), 'the same key is still refused');
    }

    public function testAKeyThatDiffersByAnAccentIsAnotherRow(): void
    {
        $connection = $this->connection();
        $this->createTables($this->schema(true));

        foreach (['abcdefghijklmnop', 'ábcdefghijklmnop'] as $key) {
            $connection->insert(Draft::TABLE, ['owner' => self::OWNER, 'draft_key' => $key, 'revision' => 1, 'payload' => $key, 'saved_at' => '2026-11-19 10:00:00', 'expires_at' => '2026-11-20 10:00:00']);
        }

        foreach (['abcdefghijklmnop', 'ábcdefghijklmnop'] as $key) {
            self::assertSame([$key], $connection->fetchFirstColumn('SELECT payload FROM '.Draft::TABLE.' WHERE draft_key = ?', [$key]), $key);
        }
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->connection?->close();
        $this->connection = null;
    }

    protected function connection(): Connection
    {
        if ($this->connection instanceof Connection) {
            return $this->connection;
        }

        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        return $this->connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
    }

    /**
     * The schema of the draft table, with the listener as the bundle registers it, or without it as before.
     */
    protected function schema(bool $listening): Schema
    {
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 2).'/src/Domain/Model'], true);
        $config->enableNativeLazyObjects(true);

        $entityManager = new EntityManager($this->connection(), $config);

        if ($listening) {
            $entityManager->getEventManager()->addEventListener(Events::loadClassMetadata, new BinaryKeysListener());
        }

        return new SchemaTool($entityManager)->getSchemaFromMetadata(array_map($entityManager->getClassMetadata(...), array_keys(BinaryKeysListener::FIELDS)));
    }

    /**
     * The draft table, created afresh: only that one, since the server is shared with the tests of other packages.
     */
    protected function createTables(Schema $schema): void
    {
        $connection = $this->connection();
        $manager = $connection->createSchemaManager();

        foreach (array_reverse($schema->getTables()) as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table->getObjectName()->toString());
        }

        foreach ($schema->getTables() as $table) {
            $manager->createTable($table);
        }
    }
}
