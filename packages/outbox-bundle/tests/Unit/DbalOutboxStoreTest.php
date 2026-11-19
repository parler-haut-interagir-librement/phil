<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Override;
use Phil\Outbox\Contract\OutboxStore;
use Phil\Outbox\Exception\InvalidRecord;
use Phil\Outbox\OutboxRecord;
use Phil\Outbox\Payload;
use Phil\Outbox\RecordId;
use Phil\Outbox\RecordState;
use Phil\Outbox\Test\OutboxStoreTestCase;
use Phil\OutboxBundle\Infrastructure\Doctrine\DbalOutboxStore;
use Phil\OutboxBundle\Infrastructure\Doctrine\OutboxTable;
use Phil\OutboxBundle\Infrastructure\Doctrine\Rows;
use Phil\OutboxBundle\Tests\Unit\Fixture\Databases;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;

#[CoversClass(DbalOutboxStore::class)]
#[CoversClass(Rows::class)]
final class DbalOutboxStoreTest extends OutboxStoreTestCase
{
    private ?Connection $connection = null;

    public function testARecordJoinsTheTransactionOfTheCaller(): void
    {
        $store = $this->createStore();
        $record = self::record('k1');

        try {
            $this->connection()->transactional(static function () use ($store, $record): never {
                $store->add($record);

                throw new RuntimeException('The write of the package failed.');
            });
        } catch (RuntimeException) {
        }

        self::assertNull($store->find($record->id));

        $this->connection()->transactional(static fn (): RecordId => $store->add($record));
        self::assertNotNull($store->find($record->id));
    }

    public function testAKeptIdempotencyKeyLeavesTheTransactionOfTheCallerAlive(): void
    {
        $store = $this->createStore();
        $first = self::record('k1');
        $store->add($first);

        $kept = $this->connection()->transactional(static function () use ($store): string {
            $id = $store->add(self::record('k1'))->value;
            $store->add(self::record('k2'));

            return $id;
        });

        self::assertSame($first->id->value, $kept);
        self::assertSame(2, $store->count(RecordState::Pending));
    }

    public function testATableOfAnotherNameIsUsed(): void
    {
        $table = new OutboxTable('events_out');
        $store = new DbalOutboxStore($connection = Databases::sqlite($table), $table);
        $record = self::record('k1');

        $store->add($record);

        self::assertSame(1, Rows::int($connection->fetchOne('SELECT COUNT(*) FROM events_out')));
        self::assertSame($record->id->value, $store->find($record->id)?->id->value);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function corruptRows(): iterable
    {
        yield 'an unknown state' => [['state' => 'lost'], 'A record of the outbox table has no known state.'];
        yield 'a moment that is not one' => [['occurred_at' => 'yesterday'], 'A column of the outbox table holds no integer.'];
        yield 'an identifier that is not one' => [['id' => 'x'], '"x" is not a record identifier: 32 lowercase hexadecimal digits.'];
    }

    /**
     * @param array<string, mixed> $columns
     */
    #[DataProvider('corruptRows')]
    public function testARowThatIsNotARecordIsRefused(array $columns, string $message): void
    {
        $store = $this->createStore();
        $record = self::record(null);
        $store->add($record);
        $this->connection()->update('phil_outbox', $columns, ['id' => $record->id->value]);

        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessageIsOrContains($message);

        $store->list(RecordState::Pending, 10);
        $store->find($record->id);
    }

    public function testANegativeOffsetListsFromTheFirstRecordAndANegativeLimitListsNone(): void
    {
        $store = $this->createStore();
        $first = self::record(null);
        $store->add($first);
        $store->add(self::record(null));

        self::assertSame([$first->id->value], self::ids($store->list(RecordState::Pending, 1, -3)));
        self::assertSame([], $store->list(RecordState::Pending, -1));
        self::assertSame([], $store->claim('c', -1, self::at('10:00:00'), self::at('10:01:00')));
        self::assertSame(0, $store->purgeDelivered(self::at('11:00:00'), -1));

        $at = self::at('10:00:00');
        $delivered = new OutboxRecord(RecordId::generate(), 'a.b', '{}', null, null, $at, $at, RecordState::Delivered, 0, null, $at);
        $store->add($delivered);

        self::assertSame(0, $store->purgeDelivered(self::at('11:00:00'), -1));
        self::assertSame(0, $store->purgeDelivered(self::at('11:00:00'), 0));
        self::assertNotNull($store->find($delivered->id));
    }

    public function testAKeyThatIsNotKeptIsInsertedWithoutReadingItBack(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $queries = [];

            public function forget(): void
            {
                $this->queries = [];
            }

            #[Override]
            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                $sql = $context['sql'] ?? null;

                if (\is_string($sql)) {
                    $this->queries[] = $sql;
                }
            }
        };
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], new Configuration()->setMiddlewares([new Middleware($logger)]));
        Databases::create($connection);
        $store = new DbalOutboxStore($connection);
        $first = self::record('k1');
        $logger->forget();

        self::assertSame($first->id->value, $store->add($first)->value);
        self::assertSame([], array_filter($logger->queries, static fn (string $sql): bool => str_starts_with($sql, 'SELECT')));

        $store->add(self::record('k1'));
        self::assertCount(1, array_filter($logger->queries, static fn (string $sql): bool => str_starts_with($sql, 'SELECT id FROM')));

        $logger->forget();
        $at = self::at('11:00:00');
        self::assertSame(0, $store->purgeDelivered($at, 10));
        self::assertSame([], $store->claim('c', 10, self::at('09:00:00'), $at));
        self::assertSame([], array_filter($logger->queries, static fn (string $sql): bool => str_starts_with($sql, 'DELETE') || str_starts_with($sql, 'UPDATE')));
    }

    public function testAClaimThatFindsNothingLeavesTheRecordsOfTheSameClaimAlone(): void
    {
        $store = $this->createStore();
        $record = self::record(null);
        $store->add($record);

        self::assertSame([$record->id->value], self::ids($store->claim('c', 10, self::at('10:00:00'), self::at('10:01:00'))));
        self::assertSame([], $store->claim('c', 10, self::at('10:00:30'), self::at('10:01:30')));
    }

    public function testTheMomentsAreReadInUtc(): void
    {
        $store = $this->createStore();
        $at = new DateTimeImmutable('2026-11-17 05:00:00', new DateTimeZone('America/Toronto'));
        $record = OutboxRecord::pending(RecordId::generate(), 'a.b', Payload::of([]), null, null, $at);
        $store->add($record);

        $kept = $store->find($record->id);
        self::assertSame('2026-11-17 10:00:00 UTC', $kept?->occurredAt->format('Y-m-d H:i:s T'));
        self::assertSame($at->getTimestamp(), $kept->availableAt->getTimestamp());
    }

    /**
     * @return iterable<string, array{AbstractPlatform, string}>
     */
    public static function platforms(): iterable
    {
        $insert = 'INSERT INTO phil_outbox (id, type, payload, ordering_key, idempotency_key, state, attempts, occurred_at, available_at, last_error, settled_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

        yield 'MySQL' => [new MySQLPlatform(), $insert.' ON DUPLICATE KEY UPDATE id = id'];
        yield 'MariaDB' => [new MariaDBPlatform(), $insert.' ON DUPLICATE KEY UPDATE id = id'];
        yield 'PostgreSQL' => [new PostgreSQLPlatform(), $insert.' ON CONFLICT (type, idempotency_key) DO NOTHING'];
        yield 'SQLite' => [new SQLitePlatform(), $insert.' ON CONFLICT (type, idempotency_key) DO NOTHING'];
    }

    #[DataProvider('platforms')]
    public function testTheInsertLeavesAKeptRecordAloneOnEveryPlatform(AbstractPlatform $platform, string $sql): void
    {
        self::assertSame($sql, new DbalOutboxStore(self::createStub(Connection::class))->insertUnlessKept($platform));
    }

    public function testTheColumnsAreReadFromWhatDriversGive(): void
    {
        self::assertSame(42, Rows::int(42));
        self::assertSame(42, Rows::int('42'));
        self::assertSame(-7, Rows::int('-7'));
        self::assertSame(999_999_999_999_999_999, Rows::int('999999999999999999'));
        self::assertSame('a', Rows::text('a'));
        self::assertNull(Rows::nullableText(null));
        self::assertSame('a', Rows::nullableText('a'));

        foreach (['4.2', '', '1e3', "1\n", '1234567890123456789', null] as $value) {
            try {
                Rows::int($value);
                self::fail(var_export($value, true).' is read as an integer.');
            } catch (InvalidRecord $invalidRecord) {
                self::assertSame('A column of the outbox table holds no integer.', $invalidRecord->getMessage());
            }
        }

        $this->expectExceptionMessageIsOrContains('A column of the outbox table holds no text.');
        Rows::nullableText(3);
    }

    /**
     * The errors written with the message of any exception: a mail server's refusal kept the address it refused.
     */
    public function testTheMessagesOfTheErrorsKeptAreClearedAndTheirClassStays(): void
    {
        $store = $this->createStore();
        $errors = [
            'RuntimeException: 550 5.1.1 <jane.doe@example.org>: Recipient address rejected',
            'Doctrine\DBAL\Exception\UniqueConstraintViolationException: Duplicate entry \'jane.doe@example.org\'',
            'Phil\Outbox\Exception\InvalidRecord: The payload has no integer "holder".',
            'Phil\Outbox\Exception\UnknownEventType (code 0): No codec rebuilds the event type "a.b": register one before recording it.',
            'RuntimeException (code 550)',
            null,
        ];
        $ids = [];

        foreach ($errors as $error) {
            $record = self::record(null);
            $store->add(new OutboxRecord($record->id, $record->type, $record->payloadJson, null, null, $record->occurredAt, $record->availableAt, RecordState::Dead, 3, $error, $record->occurredAt));
            $ids[] = $record->id;
        }

        self::assertSame(2, $this->dbal()->clearErrorMessages(1));
        self::assertSame(
            ['RuntimeException', UniqueConstraintViolationException::class, $errors[2], $errors[3], $errors[4], null],
            array_map(static fn (RecordId $id): ?string => $store->find($id)?->lastError, $ids),
        );
        self::assertSame(0, $this->dbal()->clearErrorMessages());
    }

    public function testAFullBatchIsFollowedByTheNext(): void
    {
        $store = $this->createStore();

        foreach (range(1, 5) as $n) {
            $record = self::record(null);
            $store->add(new OutboxRecord($record->id, $record->type, $record->payloadJson, null, null, $record->occurredAt, $record->availableAt, RecordState::Pending, 1, \sprintf('RuntimeException: attempt %d', $n)));
        }

        self::assertSame(5, $this->dbal()->clearErrorMessages(2));
        self::assertSame(['RuntimeException'], array_values(array_unique(array_map(static fn (OutboxRecord $record): ?string => $record->lastError, $store->list(RecordState::Pending, 10)))));
    }

    private function dbal(): DbalOutboxStore
    {
        return new DbalOutboxStore($this->connection());
    }

    private function connection(): Connection
    {
        return $this->connection ?? throw new RuntimeException('The store is not created.');
    }

    #[Override]
    protected function createStore(): OutboxStore
    {
        $this->connection = Databases::sqlite();

        return new DbalOutboxStore($this->connection);
    }

    private static function record(?string $idempotencyKey): OutboxRecord
    {
        return OutboxRecord::pending(RecordId::generate(), 'a.b', Payload::of(['n' => 1]), null, $idempotencyKey, new DateTimeImmutable('2026-11-17 10:00:00', new DateTimeZone('UTC')));
    }
}
