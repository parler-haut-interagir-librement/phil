<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Dbal;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use JsonException;
use Phil\Automations\Dbal\DbalDefinitions;
use Phil\Automations\Dbal\DbalRunStore;
use Phil\Automations\Dbal\Sql;
use Phil\Automations\Definition\AutomationDefinition;
use Phil\Automations\Definition\AutomationId;
use Phil\Automations\Definition\Step;
use Phil\Automations\Definition\StepPath;
use Phil\Automations\Definition\TriggerReference;
use Phil\Automations\Engine\Payload;
use Phil\Automations\Engine\TriggerMatch;
use Phil\Automations\Exception\InvalidDefinition;
use Phil\Automations\Exception\InvalidPayload;
use Phil\Automations\Run\AutomationRun;
use Phil\Automations\Run\CompletedStep;
use Phil\Automations\Tests\Unit\Fixture\RecordingLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Throwable;
use UnexpectedValueException;

/**
 * What the stores write to their tables and say to the server: the schema, the types, the JSON, the dialects.
 */
#[CoversClass(DbalRunStore::class)]
#[CoversClass(DbalDefinitions::class)]
#[CoversClass(Sql::class)]
final class DbalRowsTest extends TestCase
{
    public function testTheTableOfRunsHasExactlyTheseColumns(): void
    {
        $schema = new DbalRunStore(SqliteConnection::memory(), 'runs')->configureSchema(SqliteConnection::memory()->createSchemaManager()->introspectSchema());

        self::assertSame([
            'id' => ['string', 32, true, true],
            'automation_id' => ['string', 64, false, true],
            'version' => ['integer', null, false, true],
            'subject' => ['string', 255, false, false],
            'state' => ['string', 24, false, true],
            'step_cursor' => ['string', 255, false, false],
            'resume_at' => ['bigint', null, false, false],
            'attempt' => ['integer', null, false, true],
            'payload' => ['text', null, false, true],
            'completed' => ['text', null, false, true],
            'failure' => ['text', null, false, false],
            'revision' => ['integer', null, false, true],
            'started_at' => ['bigint', null, false, true],
            'updated_at' => ['bigint', null, false, true],
        ], SchemaDescriber::columns($schema->getTable('runs')));
        self::assertSame(['id'], SchemaDescriber::primaryKey($schema->getTable('runs')));
        self::assertSame(['state', 'resume_at'], SchemaDescriber::index($schema->getTable('runs'), 'runs_due'));
        self::assertSame(['updated_at'], SchemaDescriber::index($schema->getTable('runs'), 'runs_updated_at'));
    }

    public function testTheTableOfDefinitionsHasExactlyTheseColumns(): void
    {
        $schema = new DbalDefinitions(SqliteConnection::memory(), new MockClock(), 'definitions')->configureSchema(SqliteConnection::memory()->createSchemaManager()->introspectSchema());

        self::assertSame([
            'automation_id' => ['string', 64, false, true],
            'version' => ['integer', null, false, true],
            'trigger_type' => ['string', 128, false, true],
            'enabled' => ['smallint', null, false, true],
            'definition' => ['text', null, false, true],
            'published_at' => ['bigint', null, false, true],
        ], SchemaDescriber::columns($schema->getTable('definitions')));
        self::assertSame(['automation_id', 'version'], SchemaDescriber::primaryKey($schema->getTable('definitions')));
        self::assertSame(['trigger_type', 'enabled'], SchemaDescriber::index($schema->getTable('definitions'), 'definitions_trigger'));
    }

    public function testEachValueOfARunIsBoundWithItsType(): void
    {
        $logger = new RecordingLogger();
        $connection = SpiedConnection::logging($logger);
        $store = new DbalRunStore($connection);
        SqliteConnection::migrate($connection, $store->configureSchema(...));
        $run = $this->aRun();
        $string = ParameterType::STRING;
        $integer = ParameterType::INTEGER;

        $logger->records = [];
        $store->create($run);
        self::assertSame([$string, $string, $integer, $string, $string, $string, $integer, $integer, $string, $string, $string, $integer, $integer, $integer], $this->typesOfLast($logger));

        $logger->records = [];
        $store->save($run);
        self::assertSame([$string, $integer, $string, $string, $string, $integer, $integer, $string, $string, $string, $integer, $integer, $integer, $string, $integer], $this->typesOfLast($logger));

        $logger->records = [];
        $store->get($run->id);
        self::assertSame([$string], $this->typesOfLast($logger));

        $logger->records = [];
        $store->due(new DateTimeImmutable('@1800000000'), 5);
        self::assertSame([$string, $string, $integer], $this->typesOfLast($logger));

        $logger->records = [];
        $store->purge(new DateTimeImmutable('@1800000000'));
        self::assertSame([$string, $string, $integer], $this->typesOfLast($logger));
    }

    public function testEachValueOfADefinitionIsBoundWithItsType(): void
    {
        $logger = new RecordingLogger();
        $connection = SpiedConnection::logging($logger);
        $store = new DbalDefinitions($connection, new MockClock('2026-11-13 10:00:00 UTC'));
        SqliteConnection::migrate($connection, $store->configureSchema(...));
        $string = ParameterType::STRING;
        $integer = ParameterType::INTEGER;
        $definition = new AutomationDefinition(AutomationId::fromString('a'), 2, 'A', TriggerReference::of('shop.order_paid'), [Step::delay('wait', 5)]);

        $logger->records = [];
        $store->publish($definition);
        $typesOf = static fn (RecordingLogger $logger): array => array_map(static fn (array $record): array => array_values(\is_array($record['context']['types'] ?? null) ? $record['context']['types'] : []), array_values(array_filter($logger->records, static fn (array $record): bool => str_starts_with($record['message'], 'Executing statement'))));
        self::assertSame([
            [$string, $integer],
            [$string],
            [$string, $integer, $string, $integer, $string, $integer],
            [$integer, $string, $integer],
        ], array_values(array_filter($typesOf($logger), static fn (array $types): bool => [] !== $types)));
    }

    public function testADefinitionNotEnabledIsStoredAsZeroAndAnEnabledOneAsOne(): void
    {
        $connection = SqliteConnection::memory();
        $store = new DbalDefinitions($connection, new MockClock('2026-11-13 10:00:00 UTC'));
        SqliteConnection::migrate($connection, $store->configureSchema(...));

        $store->publish(new AutomationDefinition(AutomationId::fromString('a'), 1, 'A', TriggerReference::of('shop.order_paid'), [Step::delay('wait', 5)]), false);
        $store->publish(new AutomationDefinition(AutomationId::fromString('b'), 1, 'B', TriggerReference::of('shop.order_paid'), [Step::delay('wait', 5)]));

        self::assertEquals(['a' => 0, 'b' => 1], $connection->fetchAllKeyValue('SELECT automation_id, enabled FROM '.DbalDefinitions::TABLE.' ORDER BY automation_id'));
        self::assertFalse($connection->isTransactionActive());
    }

    public function testARefusedPublicationLeavesNoTransactionOpen(): void
    {
        $connection = SqliteConnection::memory();
        $store = new DbalDefinitions($connection, new MockClock('2026-11-13 10:00:00 UTC'));
        SqliteConnection::migrate($connection, $store->configureSchema(...));
        $store->publish(new AutomationDefinition(AutomationId::fromString('a'), 1, 'A', TriggerReference::of('shop.order_paid'), [Step::delay('wait', 5)]));

        try {
            $store->publish(new AutomationDefinition(AutomationId::fromString('a'), 1, 'Other', TriggerReference::of('shop.order_paid'), [Step::delay('wait', 5)]));
            self::fail('A version kept is not changed.');
        } catch (InvalidDefinition) {
            self::assertFalse($connection->isTransactionActive());
        }
    }

    public function testTheSqlOfEachDialectLeavesAnExistingRowAlone(): void
    {
        self::assertSame('INSERT INTO t (a, b) VALUES (?, ?) ON CONFLICT (a) DO NOTHING', Sql::insertUnlessPresent(new PostgreSQLPlatform(), 't', ['a', 'b'], ['a']));
        self::assertSame('INSERT INTO t (a, b, c) VALUES (?, ?, ?) ON CONFLICT (a, b) DO NOTHING', Sql::insertUnlessPresent(new PostgreSQLPlatform(), 't', ['a', 'b', 'c'], ['a', 'b']));
        self::assertSame('INSERT IGNORE INTO t (a, b) VALUES (?, ?)', Sql::insertUnlessPresent(new MariaDBPlatform(), 't', ['a', 'b'], ['a']));
        self::assertSame('INSERT OR IGNORE INTO t (a, b) VALUES (?, ?)', Sql::insertUnlessPresent(new SQLitePlatform(), 't', ['a', 'b'], ['a']));
        self::assertInstanceOf(AbstractMySQLPlatform::class, new MariaDBPlatform());
    }

    public function testARunIsCreatedWithTheConflictTargetOfItsKeyOnPostgreSql(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'runs');
        self::assertIsString($path);

        try {
            [$plain, $postgres] = SpiedConnection::postgresOverFile($path);
            SqliteConnection::migrate($plain, new DbalRunStore($plain)->configureSchema(...));
            $store = new DbalRunStore($postgres);
            $run = $this->aRun();

            self::assertTrue($store->create($run));
            self::assertFalse($store->create($run));
        } finally {
            unlink($path);
        }
    }

    public function testTheJsonOfARunKeepsItsLettersAndItsEmptyObjects(): void
    {
        [$connection, $store] = $this->store();
        $store->create($this->aRun(Payload::empty()));
        self::assertSame('{}', $connection->fetchOne('SELECT payload FROM '.DbalRunStore::TABLE));
        self::assertSame('[]', $connection->fetchOne('SELECT completed FROM '.DbalRunStore::TABLE));

        $connection->executeStatement('DELETE FROM '.DbalRunStore::TABLE);
        $run = $this->aRun(Payload::of(['name' => 'é']))->stepDone(new CompletedStep('one', 'test.act', Payload::of(['label' => 'é'])), StepPath::top(1), $this->at());
        $store->create($run);
        self::assertSame('{"name":"é"}', $connection->fetchOne('SELECT payload FROM '.DbalRunStore::TABLE));
        self::assertSame('[{"step":"one","action":"test.act","result":{"label":"é"}}]', $connection->fetchOne('SELECT completed FROM '.DbalRunStore::TABLE));

        $connection->executeStatement('DELETE FROM '.DbalRunStore::TABLE);
        $store->create($this->aRun()->stepDone(new CompletedStep('one', 'test.act', Payload::empty()), StepPath::top(1), $this->at()));
        self::assertSame('[{"step":"one","action":"test.act","result":{}}]', $connection->fetchOne('SELECT completed FROM '.DbalRunStore::TABLE));
    }

    public function testTheJsonColumnsAreReadUpToTheirDepth(): void
    {
        [$connection, $store] = $this->store();
        $run = $this->aRun();
        $store->create($run);

        $connection->executeStatement('UPDATE '.DbalRunStore::TABLE.' SET payload = ?', ['{"a":{"b":{"c":1}}}']);
        $this->assertThrowable(InvalidPayload::class, static fn (): ?AutomationRun => $store->get($run->id));

        $connection->executeStatement('UPDATE '.DbalRunStore::TABLE.' SET payload = ?', ['{"a":{"b":{"c":{}}}}']);
        $this->assertThrowable(JsonException::class, static fn (): ?AutomationRun => $store->get($run->id));

        $connection->executeStatement('UPDATE '.DbalRunStore::TABLE.' SET payload = ?, completed = ?', ['{}', '[[[[[[[1]]]]]]]']);
        $this->assertThrowable(UnexpectedValueException::class, static fn (): ?AutomationRun => $store->get($run->id));

        $connection->executeStatement('UPDATE '.DbalRunStore::TABLE.' SET completed = ?', ['[[[[[[[[1]]]]]]]]']);
        $this->assertThrowable(JsonException::class, static fn (): ?AutomationRun => $store->get($run->id));
    }

    public function testAColumnThatIsNotAnObjectOrAListIsRefused(): void
    {
        [$connection, $store] = $this->store();
        $run = $this->aRun();
        $store->create($run);

        foreach ([['"x"', '[]'], ['{}', '5'], ['5', '5']] as [$payload, $completed]) {
            $connection->executeStatement('UPDATE '.DbalRunStore::TABLE.' SET payload = ?, completed = ?', [$payload, $completed]);
            $this->assertThrowable(UnexpectedValueException::class, static fn (): ?AutomationRun => $store->get($run->id));
        }
    }

    public function testAStepDoneThatIsNotAnObjectWithAResultIsRefused(): void
    {
        [$connection, $store] = $this->store();
        $run = $this->aRun();
        $store->create($run);

        foreach (['[5]', '[{"step":"a","action":"b"}]', '[{"step":"a","action":"b","result":5}]'] as $completed) {
            $connection->executeStatement('UPDATE '.DbalRunStore::TABLE.' SET completed = ?', [$completed]);
            $this->assertThrowable(UnexpectedValueException::class, static fn (): ?AutomationRun => $store->get($run->id));
        }
    }

    public function testTheStepsDoneAreReadBackAsAList(): void
    {
        [$connection, $store] = $this->store();
        $run = $this->aRun();
        $store->create($run);
        $connection->executeStatement('UPDATE '.DbalRunStore::TABLE.' SET completed = ?', ['{"x":{"step":"a","action":"b","result":{}},"y":{"step":"c","action":"d","result":{}}}']);

        $read = $store->get($run->id);

        self::assertNotNull($read);
        self::assertSame([0, 1], array_keys($read->completed));
    }

    /**
     * @return list<ParameterType>
     */
    private function typesOfLast(RecordingLogger $logger): array
    {
        $statements = array_values(array_filter($logger->records, static fn (array $record): bool => str_starts_with($record['message'], 'Executing statement')));
        $last = end($statements);
        self::assertIsArray($last);
        $types = $last['context']['types'] ?? [];
        self::assertIsArray($types);

        return array_values(array_map(static fn (mixed $type): ParameterType => $type instanceof ParameterType ? $type : ParameterType::STRING, $types));
    }

    /**
     * @param class-string<Throwable> $class
     * @param callable(): mixed       $call
     */
    private function assertThrowable(string $class, callable $call): void
    {
        try {
            $call();
        } catch (Throwable $throwable) {
            self::assertInstanceOf($class, $throwable);

            return;
        }

        self::fail('A '.$class.' was expected.');
    }

    /**
     * @return array{Connection, DbalRunStore}
     */
    private function store(): array
    {
        $connection = SqliteConnection::memory();
        $store = new DbalRunStore($connection);
        SqliteConnection::migrate($connection, $store->configureSchema(...));

        return [$connection, $store];
    }

    private function at(): DateTimeImmutable
    {
        return new DateTimeImmutable('@1800000000');
    }

    private function aRun(?Payload $payload = null): AutomationRun
    {
        $definition = new AutomationDefinition(AutomationId::fromString('welcome'), 3, 'Welcome', TriggerReference::of('shop.order_paid'), [Step::delay('wait', 5)]);

        return AutomationRun::start($definition, new TriggerMatch('order:1', 'customer-7', $payload ?? Payload::of(['order' => '1'])), $this->at())->claimed($this->at(), $this->at()->modify('+60 seconds'));
    }
}
