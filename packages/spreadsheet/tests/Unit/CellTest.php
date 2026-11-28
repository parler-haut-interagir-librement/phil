<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Tests\Unit;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Phil\Spreadsheet\Cell;
use Phil\Spreadsheet\CellType;
use Phil\Spreadsheet\Exception\InvalidSpreadsheet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Cell::class)]
final class CellTest extends TestCase
{
    public function testEachKindKeepsItsValue(): void
    {
        self::assertSame([CellType::Text, '00123'], [Cell::text('00123')->type, Cell::text('00123')->value]);
        self::assertSame([CellType::Number, 12], [Cell::number(12)->type, Cell::number(12)->value]);
        self::assertSame(1.5, Cell::number(1.5)->value);
        self::assertSame([CellType::Boolean, false], [Cell::boolean(false)->type, Cell::boolean(false)->value]);
        self::assertSame([CellType::Empty, null], [Cell::empty()->type, Cell::empty()->value]);
    }

    public function testAnEmptyTextIsAnEmptyCell(): void
    {
        self::assertTrue(Cell::text('')->isEmpty());
        self::assertFalse(Cell::text(' ')->isEmpty());
        self::assertFalse(Cell::number(0)->isEmpty());
    }

    public function testADateDropsItsTimeAndKeepsItsZone(): void
    {
        $cell = Cell::date(new DateTime('2026-11-21 14:30:05.5', new DateTimeZone('America/Toronto')));

        self::assertSame(CellType::Date, $cell->type);
        self::assertInstanceOf(DateTimeImmutable::class, $cell->value);
        self::assertSame('2026-11-21T00:00:00.000000-05:00', $cell->value->format('Y-m-d\TH:i:s.uP'));
    }

    public function testADateAndTimeKeepsItsTime(): void
    {
        $cell = Cell::dateTime(new DateTime('2026-11-21 14:30:05.5'));

        self::assertSame(CellType::DateTime, $cell->type);
        self::assertInstanceOf(DateTimeImmutable::class, $cell->value);
        self::assertSame('14:30:05.500000', $cell->value->format('H:i:s.u'));
    }

    public function testANumberIsFinite(): void
    {
        foreach ([\INF, -\INF, \NAN] as $number) {
            try {
                Cell::number($number);
                self::fail('A number that is not finite is refused.');
            } catch (InvalidSpreadsheet $exception) {
                self::assertSame('A number cell holds a finite number.', $exception->getMessage());
            }
        }
    }

    public function testFromTypesAValueByItsPhpTypeAlone(): void
    {
        $cell = Cell::text('x');

        self::assertSame($cell, Cell::from($cell));
        self::assertSame(CellType::Empty, Cell::from(null)->type);
        self::assertSame(CellType::Text, Cell::from('42')->type);
        self::assertSame(CellType::Number, Cell::from(42)->type);
        self::assertSame(CellType::Number, Cell::from(4.2)->type);
        self::assertSame(CellType::Boolean, Cell::from(true)->type);
        self::assertSame(CellType::DateTime, Cell::from(new DateTime())->type);
    }

    public function testFromRefusesAValueWithNoCellType(): void
    {
        $this->expectException(InvalidSpreadsheet::class);
        $this->expectExceptionMessageIsOrContains('A cell cannot hold a value of type stdClass.');

        Cell::from(new stdClass());
    }
}
