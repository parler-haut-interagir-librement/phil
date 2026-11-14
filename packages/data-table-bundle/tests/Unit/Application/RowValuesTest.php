<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Unit\Application;

use DateTimeImmutable;
use DateTimeZone;
use Phil\DataTableBundle\Application\Definition\Column;
use Phil\DataTableBundle\Application\DenyingTableAccess;
use Phil\DataTableBundle\Application\EditValues;
use Phil\DataTableBundle\Application\Exception\InvalidEditValue;
use Phil\DataTableBundle\Application\NoTablePreferences;
use Phil\DataTableBundle\Application\RowValues;
use Phil\DataTableBundle\Application\State\TablePreference;
use Phil\DataTableBundle\Tests\Fixtures\Kit;
use Phil\DataTableBundle\Tests\Fixtures\Level;
use Phil\DataTableBundle\Tests\Fixtures\Status;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;

#[CoversClass(RowValues::class)]
#[CoversClass(EditValues::class)]
#[CoversClass(InvalidEditValue::class)]
#[CoversClass(DenyingTableAccess::class)]
#[CoversClass(NoTablePreferences::class)]
#[CoversClass(TablePreference::class)]
final class RowValuesTest extends TestCase
{
    public function testAValueIsReadFromAnArrayOrAnObject(): void
    {
        $values = new Kit()->values;
        $object = new stdClass();
        $object->title = 'Dune';

        self::assertSame('Dune', $values->read(['title' => 'Dune'], 'title'));
        self::assertNull($values->read([], 'title'));
        self::assertSame('Dune', $values->read($object, 'title'));
    }

    public function testAColumnReadsItsPathOrItsFunction(): void
    {
        $values = new Kit()->values;
        $row = ['title' => 'Dune', 'other' => 'x'];

        self::assertSame('Dune', $values->value($row, Column::text('title', 'T')));
        self::assertSame('x', $values->value($row, Column::text('title', 'T')->of('other')));
        self::assertSame('DUNE', $values->value($row, Column::text('title', 'T')->reading(static fn (array|object $row): string => \is_array($row) ? 'DUNE' : '')));
        self::assertNull($values->value($row, Column::block('title', 'T', 't', 'b')), 'a block reads the row itself');
        self::assertSame('x', $values->value($row, Column::block('title', 'T', 't', 'b')->of('other')));
    }

    public function testStrings(): void
    {
        $values = new Kit()->values;
        self::assertSame('2', $values->string(Level::High));
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return 'written';
            }
        };

        self::assertSame(['draft', '12', 'a', '1', 'written', '', ''], [$values->string(Status::Draft), $values->string(12), $values->string('a'), $values->string(true), $values->string($stringable), $values->string(null), $values->string(['a'])]);
    }

    public function testRawValues(): void
    {
        $edit = new Kit()->editValues;

        self::assertSame('Dune', $edit->raw(Column::text('t', 'T'), 'Dune'));
        self::assertSame('2026-03-01', $edit->raw(Column::date('d', 'D', timezone: 'UTC'), new DateTimeImmutable('2026-02-28 22:00', new DateTimeZone('America/Toronto'))));
        self::assertSame('', $edit->raw(Column::date('d', 'D'), null));
        self::assertSame(['1', '0'], [$edit->raw(Column::boolean('b', 'B'), true), $edit->raw(Column::boolean('b', 'B'), false)]);
        self::assertSame('draft', $edit->raw(Column::enum('s', 'S', Status::class), Status::Draft));
    }

    public function testConversions(): void
    {
        $edit = new Kit()->editValues;

        self::assertSame('', $edit->convert(Column::text('t', 'T'), ''));
        self::assertSame('Dune', $edit->convert(Column::text('t', 'T'), 'Dune'));
        self::assertSame(12, $edit->convert(Column::number('n', 'N'), '12'));
        self::assertSame(12.5, $edit->convert(Column::number('n', 'N'), '12.5'));
        self::assertSame(12, $edit->convert(Column::number('n', 'N', 0), '12.5'));
        self::assertNull($edit->convert(Column::number('n', 'N'), ''));
        self::assertNull($edit->convert(Column::date('d', 'D'), ''));
        $day = $edit->convert(Column::date('d', 'D', timezone: 'America/Toronto'), '2026-03-01');
        self::assertInstanceOf(DateTimeImmutable::class, $day);
        self::assertSame('2026-03-01T00:00:00-05:00', $day->format('c'));
        self::assertNull($edit->convert(Column::enum('s', 'S', ['a' => 'A']), ''));
        self::assertSame('a', $edit->convert(Column::enum('s', 'S', ['a' => 'A']), 'a'));
        self::assertSame(Status::Live, $edit->convert(Column::enum('s', 'S', Status::class), 'live'));
        self::assertSame(Level::High, $edit->convert(Column::enum('s', 'S', Level::class), '2'));
        self::assertTrue($edit->convert(Column::boolean('b', 'B'), '1'));
        self::assertFalse($edit->convert(Column::boolean('b', 'B'), '0'));
    }

    /**
     * @return iterable<string, array{Column, string}>
     */
    public static function unreadable(): iterable
    {
        yield 'word' => [Column::number('n', 'N'), 'twelve'];
        yield 'infinite' => [Column::number('n', 'N'), '1e999'];
        yield 'no such day' => [Column::date('d', 'D'), '2026-02-31'];
        yield 'not a day' => [Column::date('d', 'D'), 'tomorrow'];
        yield 'no such choice' => [Column::enum('s', 'S', ['a' => 'A']), 'b'];
        yield 'neither' => [Column::boolean('b', 'B'), 'yes'];
    }

    #[DataProvider('unreadable')]
    public function testAValueThatCannotBeRead(Column $column, string $raw): void
    {
        $this->expectException(InvalidEditValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The value sent for the column "%s" cannot be read.', $column->name));

        new Kit()->editValues->convert($column, $raw);
    }

    public function testTheDefaults(): void
    {
        self::assertFalse(new DenyingTableAccess()->isGranted('ANY'));
        $preference = new NoTablePreferences()->load('t');
        self::assertSame([[], 0], [$preference->columns, $preference->pageSize]);
        new NoTablePreferences()->save('t', new TablePreference(['a'], 5));
        self::assertSame([], new NoTablePreferences()->load('t')->columns);
    }
}
