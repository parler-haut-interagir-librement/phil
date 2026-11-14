<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Unit\Application\State;

use Phil\DataTableBundle\Application\State\TableLinks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TableLinks::class)]
final class TableLinksTest extends TestCase
{
    public function testALinkKeepsTheStateAndTheOtherParameters(): void
    {
        $links = new TableLinks('t_', ['other' => 'a b'], ['sort' => '-title', 'size' => null, 'filters' => ['q' => 'x y'], 'columns' => []]);

        self::assertSame('?other=a%20b&t_sort=-title&t_filters%5Bq%5D=x%20y', $links->href());
        self::assertSame('?other=a%20b&t_filters%5Bq%5D=x%20y&t_page=2', $links->href(['sort' => '', 'page' => 2]));
    }

    public function testAnEmptyStateIsAnEmptyQuery(): void
    {
        self::assertSame('?', new TableLinks('', [], [])->href());
    }

    public function testHiddenFieldsCarryTheNamedStateAndTheOtherParameters(): void
    {
        $links = new TableLinks('t_', ['other' => ['a' => 'b c']], ['sort' => 'title', 'size' => 5]);

        self::assertSame([['name' => 'other[a]', 'value' => 'b c'], ['name' => 't_sort', 'value' => 'title']], $links->hiddenFields(['sort']));
        self::assertSame([], new TableLinks('', [], ['sort' => ''])->hiddenFields(['sort']));
    }

    public function testAValueHoldingAnEqualSignStaysWhole(): void
    {
        self::assertSame([['name' => 'a', 'value' => 'b=c']], new TableLinks('', ['a' => 'b=c'], [])->hiddenFields([]));
    }
}
