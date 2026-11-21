<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Tests\Unit\Profile;

use DateTimeImmutable;
use Phil\ImportWizard\Plan\Reason;
use Phil\ImportWizard\Profile\ColumnType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ColumnType::class)]
final class ColumnTypeTest extends TestCase
{
    /**
     * @return iterable<string, array{ColumnType, string, string|int|bool|Reason}>
     */
    public static function cells(): iterable
    {
        yield 'text as written' => [ColumnType::Text, 'Gaëlle  B', 'Gaëlle  B'];
        yield 'integer' => [ColumnType::Integer, '42', 42];
        yield 'signed integer' => [ColumnType::Integer, '-7', -7];
        yield 'plus integer' => [ColumnType::Integer, '+7', 7];
        yield 'eighteen digits' => [ColumnType::Integer, '123456789012345678', 123456789012345678];
        yield 'nineteen digits' => [ColumnType::Integer, '1234567890123456789', Reason::NotAnInteger];
        yield 'integer with text after' => [ColumnType::Integer, '12a', Reason::NotAnInteger];
        yield 'integer with text before' => [ColumnType::Integer, 'a12', Reason::NotAnInteger];
        yield 'integer with a line break' => [ColumnType::Integer, "12\n", Reason::NotAnInteger];
        yield 'decimal with a point' => [ColumnType::Decimal, '12.50', '12.50'];
        yield 'decimal with a comma' => [ColumnType::Decimal, '-12,5', '-12.5'];
        yield 'decimal without fraction' => [ColumnType::Decimal, '+3', '+3'];
        yield 'decimal with two points' => [ColumnType::Decimal, '1.2.3', Reason::NotADecimal];
        yield 'decimal with a trailing point' => [ColumnType::Decimal, '1.', Reason::NotADecimal];
        yield 'decimal with text before' => [ColumnType::Decimal, 'x1.5', Reason::NotADecimal];
        yield 'decimal with a line break' => [ColumnType::Decimal, "1.5\n", Reason::NotADecimal];
        yield 'yes' => [ColumnType::Boolean, 'YES', true];
        yield 'oui' => [ColumnType::Boolean, 'Oui', true];
        yield 'o' => [ColumnType::Boolean, 'o', true];
        yield 'y' => [ColumnType::Boolean, 'y', true];
        yield 'true' => [ColumnType::Boolean, 'True', true];
        yield 'one' => [ColumnType::Boolean, '1', true];
        yield 'no' => [ColumnType::Boolean, 'No', false];
        yield 'non' => [ColumnType::Boolean, 'NON', false];
        yield 'n' => [ColumnType::Boolean, 'n', false];
        yield 'false' => [ColumnType::Boolean, 'false', false];
        yield 'zero' => [ColumnType::Boolean, '0', false];
        yield 'maybe' => [ColumnType::Boolean, 'maybe', Reason::NotABoolean];
        yield 'email lowered' => [ColumnType::Email, 'Ana.Lee@Example.ORG', 'ana.lee@example.org'];
        yield 'not an email' => [ColumnType::Email, 'ana.example.org', Reason::NotAnEmail];
        yield 'email too long' => [ColumnType::Email, str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 61).'.org', Reason::NotAnEmail];
        yield 'not a date' => [ColumnType::Date, '19/11/2026', Reason::NotADate];
        yield 'date with text after' => [ColumnType::Date, '2026-11-19x', Reason::NotADate];
        yield 'date with text before' => [ColumnType::Date, 'x2026-11-19', Reason::NotADate];
        yield 'date with a line break' => [ColumnType::Date, "2026-11-19\n", Reason::NotADate];
        yield 'date that does not exist' => [ColumnType::Date, '2026-02-30', Reason::NotADate];
        yield 'month thirteen' => [ColumnType::Date, '2026-13-01', Reason::NotADate];
    }

    #[DataProvider('cells')]
    public function testItReadsACell(ColumnType $type, string $cell, string|int|bool|Reason $expected): void
    {
        self::assertSame($expected, $type->parse($cell));
    }

    public function testAnEmailOfExactly254CharactersIsRead(): void
    {
        $email = str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 57).'.org';

        self::assertSame(254, \strlen($email));
        self::assertSame($email, ColumnType::Email->parse($email));
    }

    public function testAnInternationalEmailIsRead(): void
    {
        self::assertSame('élise@exemple.ca', ColumnType::Email->parse('Élise@exemple.ca'));
    }

    public function testADateIsReadAtMidnightUtc(): void
    {
        $date = ColumnType::Date->parse('2026-11-19');

        self::assertInstanceOf(DateTimeImmutable::class, $date);
        self::assertSame('2026-11-19T00:00:00+00:00', $date->format(\DATE_ATOM));
    }

    public function testValuesReadAsText(): void
    {
        self::assertSame('2026-11-19', ColumnType::text(new DateTimeImmutable('2026-11-19 15:00')));
        self::assertSame('1', ColumnType::text(true));
        self::assertSame('0', ColumnType::text(false));
        self::assertSame('12', ColumnType::text(12));
        self::assertSame('a', ColumnType::text('a'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notDates(): iterable
    {
        yield 'trailing line feed' => ["2026-11-19\n"];
        yield 'leading text' => ['x2026-11-19'];
        yield 'trailing text' => ['2026-11-19x'];
        yield 'single digits' => ['2026-1-9'];
        yield 'leading space' => [' 2026-11-19'];
        yield 'day of another month' => ['2026-02-30'];
        yield 'five digit year' => ['12026-11-19'];
        yield 'empty' => [''];
    }

    #[DataProvider('notDates')]
    public function testWhatIsNotAnExactDateIsRefused(string $cell): void
    {
        self::assertSame(Reason::NotADate, ColumnType::Date->parse($cell));
    }
}
