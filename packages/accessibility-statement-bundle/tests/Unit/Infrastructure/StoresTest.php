<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\AccessibilityStatement\Statement;
use Phil\AccessibilityStatement\StatementReader;
use Phil\AccessibilityStatementBundle\Domain\Exception\StatementNotStored;
use Phil\AccessibilityStatementBundle\Infrastructure\Config\ConfiguredStatements;
use Phil\AccessibilityStatementBundle\Infrastructure\Doctrine\DbalStatements;
use Phil\AccessibilityStatementBundle\Infrastructure\Doctrine\StatementSchemaListener;
use Phil\AccessibilityStatementBundle\Infrastructure\Doctrine\StatementTables;
use Phil\AccessibilityStatementBundle\Tests\Unit\Fixture\RecordingLogger;
use Phil\AccessibilityStatementBundle\Tests\Unit\Fixture\Statements;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The statement of the configuration, and the versions kept on SQLite in memory.
 */
#[CoversClass(ConfiguredStatements::class)]
#[CoversClass(DbalStatements::class)]
#[CoversClass(StatementTables::class)]
#[CoversClass(StatementSchemaListener::class)]
#[CoversClass(StatementNotStored::class)]
final class StoresTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach (StatementTables::configure(Schema::editor()->create())->toSql($this->connection->getDatabasePlatform()) as $sql) {
            $this->connection->executeStatement($sql);
        }
    }

    public function testTheConfigurationHoldsOneVersionReadOnly(): void
    {
        $statements = new ConfiguredStatements(Statements::complete());
        $statement = $statements->latest();
        self::assertInstanceOf(Statement::class, $statement);

        self::assertSame(3, $statement->version);
        self::assertSame($statement, $statements->find(3));
        self::assertNull($statements->find(2));
        self::assertEquals([3 => new DateTimeImmutable('2026-11-01T00:00:00+00:00')], $statements->publications(10));
        self::assertSame([3], array_keys($statements->publications(1)));
        self::assertSame([], $statements->publications(0));
        self::assertFalse($statements->writable());

        $this->expectExceptionObject(StatementNotStored::readOnly());
        $statements->add($statement);
    }

    public function testAConfigurationWithoutAStatementPublishesNone(): void
    {
        $statements = new ConfiguredStatements(null);

        self::assertNull($statements->latest());
        self::assertNull($statements->find(1));
        self::assertSame([], $statements->publications(10));
        self::assertFalse($statements->writable());
    }

    public function testVersionsAreKeptAndReadTheNewestFirst(): void
    {
        $statements = new DbalStatements($this->connection);
        $first = StatementReader::fromArray(Statements::minimal());
        $second = StatementReader::fromArray(Statements::complete());

        self::assertTrue($statements->writable());
        self::assertNull($statements->latest());
        self::assertSame([], $statements->publications(10));

        $statements->add($first);
        $statements->add($second);

        self::assertEquals($second, $statements->latest());
        self::assertEquals($first, $statements->find(1));
        self::assertNull($statements->find(2));
        self::assertEquals([3 => new DateTimeImmutable('2026-11-01T00:00:00+00:00'), 1 => new DateTimeImmutable('2026-11-01T00:00:00+00:00')], $statements->publications(10));
        self::assertSame([3], array_keys($statements->publications(1)));
        self::assertSame([], $statements->publications(0));
        self::assertSame([], $statements->publications(-1));
        self::assertSame('2026-11-01 00:00:00', $this->connection->fetchOne('SELECT published_at FROM phil_accessibility_statement WHERE version = 1'));
        self::assertSame('{"version":1,"published_at":"2026-11-01T00:00:00+00:00","standard":"wcag-2.2","level":"AA","status":"full","feedback":{"email":"accessibility@example.com"}}', $this->connection->fetchOne('SELECT document FROM phil_accessibility_statement WHERE version = 1'));
        $document = $this->connection->fetchOne('SELECT document FROM phil_accessibility_statement WHERE version = 3');
        self::assertIsString($document);
        self::assertStringContainsString('Montréal"},', $document);
    }

    public function testTheMomentOfAPublicationIsKeptInUtc(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('America/Toronto');

        try {
            $statements = new DbalStatements($this->connection);
            $statements->add(StatementReader::fromArray(['published_at' => '2026-11-01T21:30:00-05:00'] + Statements::minimal()));

            self::assertSame('2026-11-02 02:30:00', $this->connection->fetchOne('SELECT published_at FROM phil_accessibility_statement WHERE version = 1'));
            $moment = $statements->publications(1)[1] ?? null;
            self::assertInstanceOf(DateTimeImmutable::class, $moment);
            self::assertSame('2026-11-02T02:30:00+00:00', $moment->format(\DATE_ATOM), 'read back in UTC, whatever the time zone of the server');
        } finally {
            date_default_timezone_set($previous);
        }
    }

    public function testASecondVersionOfANumberIsRefused(): void
    {
        $statements = new DbalStatements($this->connection);
        $statements->add(StatementReader::fromArray(Statements::minimal()));

        try {
            $statements->add(StatementReader::fromArray(Statements::minimal()));
            self::fail('The version was published twice.');
        } catch (StatementNotStored $statementNotStored) {
            self::assertSame('Version 1 of the statement is already published.', $statementNotStored->getMessage());
            self::assertNotNull($statementNotStored->getPrevious());
        }
    }

    public function testTheConfigurationIsTheVersionInForceWhileTheTableIsEmpty(): void
    {
        $statements = new DbalStatements($this->connection, Statements::minimal(2));

        self::assertSame(2, $statements->latest()?->version);
        self::assertSame(2, $statements->find(2)?->version);
        self::assertNull($statements->find(1));
        self::assertSame([2], array_keys($statements->publications(10)));
        self::assertSame([], $statements->publications(0));

        $statements->add(StatementReader::fromArray(Statements::complete()));

        self::assertSame(3, $statements->latest()?->version);
        self::assertSame([3, 2], array_keys($statements->publications(10)));
        self::assertSame([3], array_keys($statements->publications(1)));
        self::assertSame(2, $statements->find(2)?->version);

        $statements->add(StatementReader::fromArray([...Statements::minimal(2), 'entity' => 'Stored', 'published_at' => '2026-11-05']));

        self::assertEquals([3 => new DateTimeImmutable('2026-11-01T00:00:00+00:00'), 2 => new DateTimeImmutable('2026-11-05T00:00:00+00:00')], $statements->publications(10));
        self::assertSame('Stored', $statements->find(2)?->entity);
    }

    public function testTheConfigurationTakesItsPlaceAmongTheVersions(): void
    {
        $statements = new DbalStatements($this->connection, Statements::minimal(2));
        $statements->add(StatementReader::fromArray(Statements::minimal(1)));
        $statements->add(StatementReader::fromArray(Statements::complete()));

        self::assertSame([3, 2, 1], array_keys($statements->publications(10)));
    }

    public function testARowThatNoLongerReadsIsLeftOut(): void
    {
        $logger = new RecordingLogger();
        $statements = new DbalStatements($this->connection, null, $logger);
        $statements->add(StatementReader::fromArray(Statements::minimal()));

        $this->connection->insert(StatementTables::STATEMENTS, ['version' => 2, 'published_at' => '2026-11-02 00:00:00', 'document' => '{"version": 2, "entity": "jo@example.com"}']);

        self::assertSame([2, 1], array_keys($statements->publications(10)));
        self::assertSame([], $logger->records);
        self::assertNull($statements->latest());
        self::assertNull(new DbalStatements($this->connection, Statements::minimal(5))->latest());
        self::assertNull($statements->find(2));
        self::assertSame(1, $statements->find(1)?->version);

        $warning = ['warning', 'The accessibility statement version {version} does not read as a statement: it is served as no version.', ['version' => 2]];
        self::assertSame([$warning, $warning], $logger->records);
        self::assertStringNotContainsString('jo@example.com', serialize($logger->records));
    }

    public function testADocumentThatIsNoTextDoesNotRead(): void
    {
        $logger = new RecordingLogger();
        $this->connection->insert(StatementTables::STATEMENTS, ['version' => 3, 'published_at' => '2026-11-02 00:00:00', 'document' => '']);

        self::assertNull(new DbalStatements($this->connection, null, $logger)->find(3));
        self::assertSame([['warning', 'The accessibility statement version {version} does not read as a statement: it is served as no version.', ['version' => 3]]], $logger->records);
    }

    public function testTheTableIsAddedToTheSchemaOfItsConnection(): void
    {
        $schema = StatementTables::configure(Schema::editor()->create());
        $table = $schema->getTable(StatementTables::STATEMENTS);

        self::assertCount(3, $table->getColumns());
        self::assertSame(
            [['integer', true], ['datetime_utc_immutable', true], ['text', true]],
            array_map(static fn (string $name): array => [$table->getColumn($name)->getTypeName(), $table->getColumn($name)->getNotnull()], ['version', 'published_at', 'document']),
        );
        self::assertSame($schema, StatementTables::configure($schema));
        self::assertNotNull($table->getPrimaryKeyConstraint());

        $other = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach ([[$this->connection, true], [$other, false]] as [$connection, $added]) {
            $manager = self::createStub(EntityManagerInterface::class);
            $manager->method('getConnection')->willReturn($connection);
            $event = new GenerateSchemaEventArgs($manager, Schema::editor()->create());

            new StatementSchemaListener($this->connection)->postGenerateSchema($event);

            self::assertSame($added, $event->getSchema()->hasTable(StatementTables::STATEMENTS));
        }
    }

    public function testThePublicationIsKeptToTheSecond(): void
    {
        $statements = new DbalStatements($this->connection);
        $statements->add(StatementReader::fromArray([...Statements::minimal(), 'published_at' => '2026-11-01T12:34:56+00:00']));

        self::assertEquals(new DateTimeImmutable('2026-11-01T12:34:56+00:00'), $statements->latest()?->publishedAt);
    }
}
