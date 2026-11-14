<?php

declare(strict_types=1);

namespace Phil\StockroomBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Phil\StockroomBundle\Infrastructure\Dbal\Row;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

#[CoversClass(Row::class)]
final class RowTest extends TestCase
{
    public function testTheColumnsAreReadWithTheirTypes(): void
    {
        $row = new Row(['name' => 'Shelf', 'parent' => null, 'code' => 'SH', 'quantity' => '42', 'native' => 7, 'negative' => '-3']);

        self::assertSame('Shelf', $row->string('name'));
        self::assertNull($row->optional('parent'));
        self::assertNull($row->optional('missing'));
        self::assertSame('SH', $row->optional('code'));
        self::assertSame(42, $row->integer('quantity'));
        self::assertSame(7, $row->integer('native'));
        self::assertSame(-3, $row->integer('negative'));
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function refused(): iterable
    {
        yield 'a number as text' => ['string', 12];
        yield 'a missing text' => ['string', null];
        yield 'an optional number' => ['optional', 12];
        yield 'a decimal' => ['integer', '1.5'];
        yield 'a word' => ['integer', 'twelve'];
        yield 'an empty integer' => ['integer', ''];
        yield 'a line feed after the digits' => ['integer', "12\n"];
        yield 'a sign alone' => ['integer', '-'];
        yield 'text before the digits' => ['integer', 'x12'];
        yield 'too many digits' => ['integer', '12345678901234567890'];
        yield 'a float' => ['integer', 1.0];
        yield 'a missing integer' => ['integer', null];
    }

    #[DataProvider('refused')]
    public function testAColumnOfAnotherTypeIsRefused(string $method, mixed $value): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/^The column "value" of the stockroom is not a (text|whole number)\.$/');
        $row = new Row(['value' => $value]);
        match ($method) {
            'string' => $row->string('value'),
            'optional' => $row->optional('value'),
            default => $row->integer('value'),
        };
    }

    public function testTheLongestIntegerIsRead(): void
    {
        self::assertSame(1_234_567_890_123_456_789, new Row(['value' => '1234567890123456789'])->integer('value'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function moments(): iterable
    {
        yield 'with microseconds' => ['2026-11-13 15:00:00.123456'];
        yield 'on a second' => ['2026-11-13 15:00:00.000000'];
        yield 'the epoch' => ['1970-01-01 00:00:00.000000'];
        yield 'just before the epoch' => ['1969-12-31 23:59:59.500000'];
        yield 'long before the epoch' => ['1901-03-04 05:06:07.000001'];
    }

    #[DataProvider('moments')]
    public function testAMomentIsKeptToTheMicrosecondInUtc(string $utc): void
    {
        $moment = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        $read = new Row(['at' => (string) Row::microseconds($moment->setTimezone(new DateTimeZone('America/Toronto')))])->moment('at');

        self::assertSame($utc, $read->format('Y-m-d H:i:s.u'));
        self::assertSame('+00:00', $read->format('P'));
    }

    public function testTheMicrosecondsOfAMoment(): void
    {
        self::assertSame(1_763_046_000_123_456, Row::microseconds(new DateTimeImmutable('2025-11-13 15:00:00.123456 UTC')));
        self::assertSame(-500_000, Row::microseconds(new DateTimeImmutable('1969-12-31 23:59:59.5 UTC')));
    }
}
