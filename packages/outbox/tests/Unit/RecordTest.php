<?php

declare(strict_types=1);

namespace Phil\Outbox\Tests\Unit;

use DateTimeImmutable;
use LogicException;
use Phil\Outbox\Exception\InvalidRecord;
use Phil\Outbox\OutboxRecord;
use Phil\Outbox\Payload;
use Phil\Outbox\RecordId;
use Phil\Outbox\RecordState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(OutboxRecord::class)]
#[CoversClass(RecordId::class)]
#[CoversClass(RecordState::class)]
#[CoversClass(InvalidRecord::class)]
final class RecordTest extends TestCase
{
    public function testAnIdentifierIs32HexadecimalDigits(): void
    {
        $id = RecordId::generate();

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $id->value);
        self::assertSame($id->value, (string) $id);
        self::assertNotSame($id->value, RecordId::generate()->value);
        self::assertTrue($id->equals(RecordId::fromString($id->value)));
        self::assertFalse($id->equals(RecordId::generate()));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedIdentifiers(): iterable
    {
        yield 'too short' => [str_repeat('a', 31)];
        yield 'too long' => [str_repeat('a', 33)];
        yield 'uppercase' => [str_repeat('A', 32)];
        yield 'a newline' => [str_repeat('a', 32)."\n"];
    }

    #[DataProvider('refusedIdentifiers')]
    public function testAnIdentifierOfAnotherShapeIsRefused(string $value): void
    {
        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a record identifier: 32 lowercase hexadecimal digits.', $value));

        RecordId::fromString($value);
    }

    public function testALongIdentifierIsCutInTheMessage(): void
    {
        $this->expectExceptionMessageIsOrContains('"'.str_repeat('z', 39).'…" is not a record identifier');

        RecordId::fromString(str_repeat('z', 50));
    }

    public function testAPendingRecordIsAvailableWhenItOccurs(): void
    {
        $id = RecordId::generate();
        $at = new DateTimeImmutable('2026-11-17 10:00:00 UTC');
        $record = OutboxRecord::pending($id, 'sets.set_completed-v2', Payload::of(['set' => 's']), 'set:42/holder~7', '!', $at);

        self::assertSame($id, $record->id);
        self::assertSame('sets.set_completed-v2', $record->type);
        self::assertSame('{"set":"s"}', $record->payloadJson);
        self::assertSame(['set' => 's'], $record->payload()->values);
        self::assertSame('set:42/holder~7', $record->orderingKey);
        self::assertSame('!', $record->idempotencyKey);
        self::assertSame($at, $record->occurredAt);
        self::assertSame($at, $record->availableAt);
        self::assertSame(RecordState::Pending, $record->state);
        self::assertSame(0, $record->attempts);
        self::assertNull($record->lastError);
        self::assertNull($record->settledAt);
    }

    public function testTheLongestTypeAndKeysAreAccepted(): void
    {
        $type = str_repeat('a', 120);
        $key = str_repeat('~', 190);
        $record = OutboxRecord::pending(RecordId::generate(), $type, Payload::of([]), $key, $key, new DateTimeImmutable());

        self::assertSame($type, $record->type);
        self::assertSame($key, $record->orderingKey);
    }

    /**
     * @return iterable<string, array{string, ?string, ?string, string}>
     */
    public static function refusedRecords(): iterable
    {
        $type = '" is not an event type: 1 to 120 lowercase letters and digits, in words joined by ".", "_" or "-".';

        yield 'an empty type' => ['', null, null, '"'.$type];
        yield 'an uppercase type' => ['Sets.done', null, null, '"Sets.done'.$type];
        yield 'a doubled separator' => ['sets..done', null, null, '"sets..done'.$type];
        yield 'a trailing separator' => ['sets.', null, null, '"sets.'.$type];
        yield 'a leading separator' => ['.sets', null, null, '".sets'.$type];
        yield 'a space' => ['sets done', null, null, '"sets done'.$type];
        yield 'a newline after the type' => ["sets\n", null, null, "\"sets\n".$type];
        yield 'a long type' => [str_repeat('a', 121), null, null, '"'.str_repeat('a', 121).$type];
        yield 'a very long type' => [str_repeat('a', 140), null, null, '"'.str_repeat('a', 129).'…'.$type];
        yield 'an empty ordering key' => ['a', '', null, '"" is not an ordering key: 1 to 190 visible ASCII characters.'];
        yield 'a space in the ordering key' => ['a', 'set 1', null, '"set 1" is not an ordering key: 1 to 190 visible ASCII characters.'];
        yield 'a newline after the ordering key' => ['a', "k\n", null, "\"k\n\" is not an ordering key: 1 to 190 visible ASCII characters."];
        yield 'an accent in the idempotency key' => ['a', 'k', 'é', '"é" is not an idempotency key: 1 to 190 visible ASCII characters.'];
        yield 'a long idempotency key' => ['a', null, str_repeat('k', 191), '"'.str_repeat('k', 191).'" is not an idempotency key: 1 to 190 visible ASCII characters.'];
        yield 'a very long idempotency key' => ['a', null, str_repeat('k', 210), '"'.str_repeat('k', 199).'…" is not an idempotency key: 1 to 190 visible ASCII characters.'];
    }

    #[DataProvider('refusedRecords')]
    public function testATypeOrAKeyOutOfBoundsIsRefused(string $type, ?string $orderingKey, ?string $idempotencyKey, string $message): void
    {
        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessageIsOrContains($message);

        OutboxRecord::pending(RecordId::generate(), $type, Payload::of([]), $orderingKey, $idempotencyKey, new DateTimeImmutable());
    }

    public function testAnErrorKeepsTheClassAndTheMessageOfTheException(): void
    {
        self::assertSame('RuntimeException: The listener failed.', OutboxRecord::error(new RuntimeException('The listener failed.')));
    }

    public function testAnErrorIsCutOnACharacterBoundary(): void
    {
        $error = OutboxRecord::error(new LogicException('a'.str_repeat('é', 300)));

        self::assertSame(499, \strlen($error));
        self::assertSame('LogicException: a'.str_repeat('é', 241), $error);
    }

    public function testAnErrorOfInvalidUtf8IsMadeValid(): void
    {
        self::assertSame('LogicException: a?b', OutboxRecord::error(new LogicException("a\xC3b")));
    }

    public function testAnErrorOfExactly500BytesIsKept(): void
    {
        $message = str_repeat('a', 500 - \strlen('LogicException: '));

        self::assertSame('LogicException: '.$message, OutboxRecord::error(new LogicException($message)));
    }

    public function testAKeptPayloadThatIsNotOneIsRefusedWhenRead(): void
    {
        $record = new OutboxRecord(RecordId::generate(), 'a', '{"a":[1]}', null, null, new DateTimeImmutable(), new DateTimeImmutable());

        $this->expectException(InvalidRecord::class);

        $record->payload();
    }

    public function testTheStatesAreNamedAsTheStoresKeepThem(): void
    {
        self::assertSame(['pending', 'delivered', 'dead'], array_map(static fn (RecordState $state): string => $state->value, RecordState::cases()));
    }
}
