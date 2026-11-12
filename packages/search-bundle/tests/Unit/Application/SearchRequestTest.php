<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Unit\Application;

use Phil\SearchBundle\Application\SearchRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SearchRequest::class)]
final class SearchRequestTest extends TestCase
{
    public function testAQueryStringIsReadAsAsked(): void
    {
        $request = SearchRequest::fromQuery(['q' => '  jeu de rôle ', 'filter' => ['genre' => ['jdr', 'plateau', 'jdr'], 'year' => '2026', 'other' => ['x']], 'page' => '3'], ['genre', 'year', 'locale'], 10);

        self::assertSame(['jeu de rôle', ['genre' => ['jdr', 'plateau'], 'year' => ['2026']], 3, 10], [$request->text, $request->selection, $request->page, $request->perPage]);
        self::assertTrue($request->hasFilters());
        self::assertTrue($request->isSelected('genre', 'plateau'));
        self::assertFalse($request->isSelected('genre', 'cartes'));
        self::assertFalse($request->isSelected('locale', 'fr'));
        self::assertSame([['facet' => 'genre', 'value' => 'jdr'], ['facet' => 'genre', 'value' => 'plateau'], ['facet' => 'year', 'value' => '2026']], $request->selected());

        $query = $request->query(['genre', 'year'], ['locale' => 'fr', 'genre' => 'ignored'], 'fr');
        self::assertSame(['jeu de rôle', ['locale' => 'fr', 'genre' => ['jdr', 'plateau'], 'year' => ['2026']], 10, 20, ['genre', 'year'], 'fr'], [$query->text, $query->filters, $query->limit, $query->offset, $query->facets, $query->language()]);
        self::assertSame(0, SearchRequest::fromQuery([], [])->query([])->offset);
        self::assertSame(20, SearchRequest::fromQuery([], [])->query([])->limit);
    }

    public function testWhatIsNotAskedRightIsLeftOut(): void
    {
        foreach ([
            [['q' => ['x']], '', [], 1],
            [['q' => "\xFF"], '', [], 1],
            [['q' => str_repeat('é', 250)], str_repeat('é', 200), [], 1],
            [['page' => '0'], '', [], 1],
            [['page' => '-2'], '', [], 1],
            [['page' => '12345'], '', [], 1],
            [['page' => '9999'], '', [], 1000],
            [['page' => '2a'], '', [], 1],
            [['page' => 2], '', [], 1],
            [['page' => '1000'], '', [], 1000],
            [['filter' => 'x'], '', [], 1],
            [['filter' => ['genre' => ['', ['x'], 7, "\xFF", str_repeat('a', 201), str_repeat('é', 200)]]], '', ['genre' => [str_repeat('é', 200)]], 1],
        ] as [$parameters, $text, $selection, $page]) {
            $request = SearchRequest::fromQuery($parameters, ['genre']);
            self::assertSame([$text, $selection, $page], [$request->text, $request->selection, $request->page], json_encode($parameters, \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_THROW_ON_ERROR));
        }

        self::assertCount(100, SearchRequest::fromQuery(['filter' => ['genre' => array_map(strval(...), range(1, 150))]], ['genre'])->selection['genre']);
        self::assertFalse(SearchRequest::fromQuery([], ['genre'])->hasFilters());
        self::assertSame([1, 1000], [SearchRequest::fromQuery([], [], 0)->perPage, SearchRequest::fromQuery([], [], 5000)->perPage]);
    }

    public function testFiltersAreRemovedOneAtATimeOrAllTogether(): void
    {
        $request = SearchRequest::fromQuery(['q' => 'jeu & dés', 'filter' => ['genre' => ['jdr', 'plateau'], 'year' => ['2026']], 'page' => '4'], ['genre', 'year']);

        self::assertSame('?q=jeu%20%26%20d%C3%A9s&filter%5Bgenre%5D%5B0%5D=jdr&filter%5Bgenre%5D%5B1%5D=plateau&filter%5Byear%5D%5B0%5D=2026&page=4', $request->queryString());
        self::assertSame('?q=jeu%20%26%20d%C3%A9s&filter%5Bgenre%5D%5B0%5D=plateau&filter%5Byear%5D%5B0%5D=2026', $request->without('genre', 'jdr')->queryString());
        self::assertSame('?q=jeu%20%26%20d%C3%A9s&filter%5Bgenre%5D%5B0%5D=jdr&filter%5Bgenre%5D%5B1%5D=plateau', $request->without('year', '2026')->queryString());
        self::assertSame(['genre' => ['jdr', 'plateau'], 'year' => ['2026']], $request->without('genre', 'none')->selection);
        self::assertSame(['genre' => ['jdr', 'plateau'], 'year' => ['2026']], $request->without('locale', 'fr')->selection);
        self::assertSame('?q=jeu%20%26%20d%C3%A9s', $request->withoutFilters()->queryString());
        self::assertSame('?', SearchRequest::fromQuery(['page' => '2'], [])->withoutFilters()->queryString());
        self::assertSame([2, 1, 1000], [$request->onPage(2)->page, $request->onPage(0)->page, $request->onPage(5000)->page]);
        self::assertSame([$request->text, $request->selection, $request->perPage], [$request->onPage(2)->text, $request->onPage(2)->selection, $request->onPage(2)->perPage]);
        self::assertSame(20, $request->without('genre', 'jdr')->perPage);
        self::assertSame(20, $request->withoutFilters()->perPage);
    }

    public function testAPageIsDigitsAloneAndTheFirstPageIsBackWhenTheSelectionChanges(): void
    {
        self::assertSame(1, SearchRequest::fromQuery(['page' => "5\n"], [])->page);
        self::assertSame(5, SearchRequest::fromQuery(['page' => '5'], [])->page);

        $request = SearchRequest::fromQuery(['page' => '3', 'filter' => ['genre' => ['a', 'b']]], ['genre'], 10);

        self::assertSame(3, $request->page);
        self::assertSame(1, $request->without('genre', 'a')->page);
        self::assertSame(1, $request->withoutFilters()->page);
        self::assertSame(0, $request->without('genre', 'a')->query([])->offset);
        self::assertSame(0, $request->withoutFilters()->query([])->offset);
    }
}
