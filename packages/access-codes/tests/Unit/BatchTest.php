<?php

declare(strict_types=1);

namespace Phil\AccessCodes\Tests\Unit;

use DateTimeImmutable;
use Phil\AccessCodes\Batch;
use Phil\AccessCodes\BatchCounts;
use Phil\AccessCodes\Exception\InvalidConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Batch::class)]
#[CoversClass(BatchCounts::class)]
final class BatchTest extends TestCase
{
    public function testItHoldsWhatItWasGiven(): void
    {
        $created = new DateTimeImmutable('2026-11-13 10:00:00');
        $batch = new Batch('b-1_X', 'Convention cards', 100, $created, $created->modify('+1 day'));

        self::assertSame('b-1_X', $batch->id);
        self::assertSame('Convention cards', $batch->name);
        self::assertSame(100, $batch->size);
        self::assertSame($created, $batch->createdAt);
        self::assertFalse($batch->isRevoked());
        self::assertTrue(new Batch('b', 'n', 1, $created, revokedAt: $created)->isRevoked());
    }

    public function testItExpiresAtItsExpiry(): void
    {
        $created = new DateTimeImmutable('2026-11-13 10:00:00');
        $batch = new Batch('b', 'n', 1, $created, $created->modify('+1 hour'));

        self::assertFalse($batch->isExpiredAt($created->modify('+59 minutes')));
        self::assertTrue($batch->isExpiredAt($created->modify('+1 hour')));
        self::assertFalse(new Batch('b', 'n', 1, $created)->isExpiredAt($created->modify('+100 years')));
    }

    /**
     * @return iterable<string, array{string, string, int, string|null, string}>
     */
    public static function invalid(): iterable
    {
        $id = 'A batch identifier is 1 to 64 letters, digits, hyphens and underscores.';
        $name = 'A batch name is 1 to 120 characters of UTF-8.';
        $size = 'A batch holds 1 to 1000000 codes.';

        yield 'an empty identifier' => ['', 'n', 1, null, $id];
        yield 'an identifier of 65' => [str_repeat('a', 65), 'n', 1, null, $id];
        yield 'an identifier with a dot' => ['a.b', 'n', 1, null, $id];
        yield 'an identifier with a newline' => ["ab\n", 'n', 1, null, $id];
        yield 'a blank name' => ['b', '  ', 1, null, $name];
        yield 'a name of 121' => ['b', str_repeat('é', 121), 1, null, $name];
        yield 'a name outside UTF-8' => ['b', "\xFF", 1, null, $name];
        yield 'no code' => ['b', 'n', 0, null, $size];
        yield 'too many codes' => ['b', 'n', 1_000_001, null, $size];
        yield 'an expiry at creation' => ['b', 'n', 1, '+0 seconds', 'A batch expires after its creation.'];
        yield 'an expiry before creation' => ['b', 'n', 1, '-1 second', 'A batch expires after its creation.'];
    }

    #[DataProvider('invalid')]
    public function testItRefusesValuesOutOfBounds(string $id, string $name, int $size, ?string $expiry, string $message): void
    {
        $created = new DateTimeImmutable('2026-11-13 10:00:00');

        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Batch($id, $name, $size, $created, null === $expiry ? null : $created->modify($expiry));
    }

    public function testItAcceptsTheBounds(): void
    {
        $created = new DateTimeImmutable('2026-11-13 10:00:00');

        self::assertSame(1_000_000, new Batch(str_repeat('a', 64), str_repeat('é', 120), 1_000_000, $created, $created->modify('+1 second'))->size);
    }

    public function testTheUnusedCodesAreNeitherRedeemedNorRevoked(): void
    {
        $counts = new BatchCounts(10, 3, 2);

        self::assertSame(10, $counts->total);
        self::assertSame(3, $counts->redeemed);
        self::assertSame(2, $counts->revoked);
        self::assertSame(5, $counts->unused());
    }
}
