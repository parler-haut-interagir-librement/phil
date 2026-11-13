<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Tests\Unit\Dbal;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Override;
use Phil\IncidentMode\Activation;
use Phil\IncidentMode\Contract\ActivationStore;
use Phil\IncidentMode\Dbal\DbalActivationStore;
use Phil\IncidentMode\Dbal\IncidentTables;
use Phil\IncidentMode\LiftReason;
use Phil\IncidentMode\Restriction;
use Phil\IncidentMode\Tests\Unit\ActivationStoreTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(DbalActivationStore::class)]
#[CoversClass(IncidentTables::class)]
#[CoversClass(Activation::class)]
final class DbalActivationStoreTest extends ActivationStoreTestCase
{
    private ?Connection $connection = null;

    public function testTheTableHasItsColumnsAndIndexes(): void
    {
        $table = IncidentTables::table();

        self::assertSame(IncidentTables::ACTIVATIONS, $table->getObjectName()->toString());
        self::assertTrue($table->getColumn('id')->getFixed());
        self::assertSame(32, $table->getColumn('id')->getLength());
        self::assertSame(64, $table->getColumn('preset')->getLength());
        self::assertSame(190, $table->getColumn('started_by')->getLength());
        self::assertSame(190, $table->getColumn('lifted_by')->getLength());
        self::assertSame(16, $table->getColumn('lift_reason')->getLength());
        foreach (['id', 'preset', 'restrictions', 'started_at', 'ends_at'] as $required) {
            self::assertTrue($table->getColumn($required)->getNotnull(), $required);
        }

        self::assertFalse($table->getColumn('started_by')->getNotnull());
        self::assertFalse($table->getColumn('lifted_at')->getNotnull());
        self::assertFalse($table->getColumn('reason')->getNotnull());
        self::assertTrue($table->hasIndex('idx_phil_incident_in_force'));
        self::assertTrue($table->hasIndex('idx_phil_incident_started'));
    }

    public function testTheMomentsAreReadInUtc(): void
    {
        $store = $this->store();
        $store->add(new Activation(
            str_repeat('b', 32),
            'read-only',
            [Restriction::named('read-only')],
            new DateTimeImmutable('2026-11-13 05:00:00', new DateTimeZone('America/Toronto')),
            new DateTimeImmutable('2026-11-13 07:00:00', new DateTimeZone('America/Toronto')),
            null,
        ));

        self::assertSame('2026-11-13T10:00:00+00:00', $this->found($store, str_repeat('b', 32))->startedAt->format(\DATE_ATOM));
        $store->lift(str_repeat('b', 32), new DateTimeImmutable('2026-11-13 06:00:00', new DateTimeZone('America/Toronto')), null, LiftReason::Manual);
        self::assertSame('2026-11-13T11:00:00+00:00', $this->found($store, str_repeat('b', 32))->liftedAt?->format(\DATE_ATOM));
    }

    public function testTheSchemaGainsTheTableOnce(): void
    {
        $schema = IncidentTables::configure(Schema::editor()->create());

        self::assertTrue($schema->hasTable(IncidentTables::ACTIVATIONS));
        self::assertSame($schema, IncidentTables::configure($schema));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function unreadableRows(): iterable
    {
        yield 'a bad restriction' => [['restrictions' => 'Signups']];
        yield 'no restriction' => [['restrictions' => '']];
        yield 'a bad lifting reason' => [['lifted_at' => '2026-11-13 11:00:00', 'lift_reason' => 'storm']];
        yield 'a lifting without a reason' => [['lifted_at' => '2026-11-13 11:00:00']];
        yield 'an actor that is an address' => [['started_by' => 'someone@example.org']];
        yield 'an end before the start' => [['ends_at' => '2026-11-13 09:00:00']];
        yield 'a numeric preset' => [['preset' => 12]];
    }

    /**
     * @param array<string, mixed> $change
     */
    #[DataProvider('unreadableRows')]
    public function testARowThatNoLongerReadsIsLeftOut(array $change): void
    {
        $store = $this->store();
        $store->add($kept = $this->activation('a', '10:00', '12:00'));

        $connection = $this->connection;
        self::assertInstanceOf(Connection::class, $connection);
        $connection->insert(IncidentTables::ACTIVATIONS, [
            'id' => str_repeat('b', 32),
            'preset' => 'read-only',
            'restrictions' => 'read-only',
            'started_at' => '2026-11-13 10:00:00',
            'ends_at' => '2026-11-13 12:00:00',
            ...$change,
        ]);

        self::assertNull($store->find(str_repeat('b', 32)));
        self::assertSame([$kept->id], array_map(static fn (Activation $activation): string => $activation->id, $store->history(10)));
        self::assertList($store->history(10));
    }

    #[Override]
    protected function store(): ActivationStore
    {
        $this->connection = $this->connect();
        $manager = $this->connection->createSchemaManager();

        if ($manager->tablesExist([IncidentTables::ACTIVATIONS])) {
            $manager->dropTable(IncidentTables::ACTIVATIONS);
        }

        $manager->createTable(IncidentTables::table());

        return new DbalActivationStore($this->connection);
    }

    protected function connect(): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }
}
