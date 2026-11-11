<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Unit\Sort;

use Phil\Enums\Sort\SortDirection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ValueError;

/**
 * @internal
 */
#[CoversClass(SortDirection::class)]
final class SortDirectionTest extends TestCase
{
    #[Test]
    public function aDirectionIsReadLeniently(): void
    {
        self::assertSame(
            [SortDirection::Ascending, SortDirection::Descending, SortDirection::Ascending, SortDirection::Descending, SortDirection::Ascending, SortDirection::Descending, SortDirection::Descending],
            [SortDirection::parse('asc'), SortDirection::parse('desc'), SortDirection::parse('ascending'), SortDirection::parse('down'), SortDirection::parse('up'), SortDirection::parse('descending'), SortDirection::parse('DESC', ignoreCase: true)],
        );
        self::assertSame(['asc', 'desc'], [SortDirection::Ascending->value, SortDirection::Descending->value]);

        $this->expectException(ValueError::class);
        SortDirection::parse('DESC');
    }

    #[Test]
    public function aDirectionSortsCompilesAndTurns(): void
    {
        self::assertSame([SortDirection::Descending, SortDirection::Ascending], [SortDirection::descendingIf(true), SortDirection::descendingIf(false)]);
        self::assertSame([false, true], [SortDirection::Ascending->isDescending(), SortDirection::Descending->isDescending()]);
        self::assertSame([SortDirection::Descending, SortDirection::Ascending], [SortDirection::Ascending->reverse(), SortDirection::Descending->reverse()]);
        self::assertSame(['ASC', 'DESC'], [SortDirection::Ascending->sql(), SortDirection::Descending->sql()]);
        self::assertSame([-1, 1, 0, 1, -1, 0], [SortDirection::Ascending->compare(1, 2), SortDirection::Ascending->compare('b', 'a'), SortDirection::Ascending->compare(3, 3), SortDirection::Descending->compare(1, 2), SortDirection::Descending->compare('b', 'a'), SortDirection::Descending->compare(3, 3)]);

        $values = [3, 1, 2];
        usort($values, SortDirection::Descending->compare(...));
        self::assertSame([3, 2, 1], $values);
    }
}
