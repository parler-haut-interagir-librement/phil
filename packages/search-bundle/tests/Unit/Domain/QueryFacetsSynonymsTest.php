<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Unit\Domain;

use Phil\SearchBundle\Domain\Exception\InvalidSearch;
use Phil\SearchBundle\Domain\ValueObject\FacetValue;
use Phil\SearchBundle\Domain\ValueObject\Phrase;
use Phil\SearchBundle\Domain\ValueObject\SearchQuery;
use Phil\SearchBundle\Domain\ValueObject\SearchResult;
use Phil\SearchBundle\Domain\ValueObject\SynonymRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SearchQuery::class)]
#[CoversClass(SearchResult::class)]
#[CoversClass(FacetValue::class)]
#[CoversClass(Phrase::class)]
#[CoversClass(SynonymRule::class)]
final class QueryFacetsSynonymsTest extends TestCase
{
    public function testAQueryCarriesFacetsListsOfValuesAndALanguage(): void
    {
        $query = SearchQuery::of('Jeu de rôle', ['genre' => ['jdr', 'plateau'], 'locale' => 'fr_CA'], facets: ['genre', 'year', 'genre']);

        self::assertSame(['genre', 'year'], $query->facets);
        self::assertSame(['genre' => ['jdr', 'plateau'], 'locale' => 'fr_CA'], $query->filters);
        self::assertSame(['locale' => 'fr_CA'], $query->filtersWithout('genre'));
        self::assertSame($query->filters, $query->filtersWithout('none'));
        self::assertSame('fr', $query->language());
        self::assertSame('en', SearchQuery::of('x', ['locale' => 'fr'], language: 'EN-gb')->language());
        self::assertSame('pt', SearchQuery::of('x', language: 'pt')->language());
        self::assertSame('fil', SearchQuery::of('x', ['locale' => 'fil-PH'])->language());
        self::assertNull(SearchQuery::of('x', ['locale' => ['fr', 'en']])->language());
        self::assertNull(SearchQuery::of('x', ['locale' => 'français'])->language());
        self::assertNull(SearchQuery::of('x', ['locale' => 7])->language());
        self::assertNull(SearchQuery::of('x')->language());
        self::assertCount(SearchQuery::MAX_FACETS, SearchQuery::of('', facets: array_map(static fn (int $n): string => 'f'.$n, range(1, 20)))->facets);
        self::assertSame(range(1, 100), SearchQuery::of('', ['a' => range(1, 100)])->filters['a']);
    }

    public function testTermsAreTheWordsUntilSynonymsExpandThem(): void
    {
        $query = SearchQuery::of('Jeu  de RÔLE jeu');

        self::assertSame([['jeu'], ['de'], ['rôle']], $query->terms());

        $expanded = $query->withTerms([[' Jeu  de Rôle ', 'JDR', 'jdr', ''], ['x']]);

        self::assertSame([['jeu de rôle', 'jdr'], ['x']], $expanded->terms());
        self::assertSame([$query->text, $query->filters, $query->limit, $query->offset, $query->facets, $query->language()], [$expanded->text, $expanded->filters, $expanded->limit, $expanded->offset, $expanded->facets, $expanded->language()]);
        self::assertSame('fr', SearchQuery::of('x', language: 'fr')->withTerms([['x']])->language());
        self::assertCount(32, $query->withTerms([array_map(static fn (int $n): string => 'w'.$n, range(1, 32))])->terms()[0]);
        self::assertSame([], $query->withTerms([])->terms());
    }

    public function testBadFacetsListsLanguagesAndTermsAreRefused(): void
    {
        foreach ([
            [static fn (): SearchQuery => SearchQuery::of('x', ['genre' => []]), 'A filter is a value or a list of 1 to 100 values; the filter "genre" holds 0.'],
            [static fn (): SearchQuery => SearchQuery::of('x', ['genre' => range(0, 100)]), 'A filter is a value or a list of 1 to 100 values; the filter "genre" holds 101.'],
            [static fn (): SearchQuery => SearchQuery::of('x', facets: ['Genre']), 'A field is 1 to 64 lower-case letters, digits or "_", starting with a letter; "Genre" is not.'],
            [static fn (): SearchQuery => SearchQuery::of('x', facets: array_map(static fn (int $n): string => 'f'.$n, range(0, 20))), 'A search counts 20 facets at most; 21 asked.'],
            [static fn (): SearchQuery => SearchQuery::of('x', language: 'français'), 'A language is a tag such as "fr" or "fr-CA"; "français" is not.'],
            [static fn (): SearchQuery => SearchQuery::of('x', language: "fr\n"), "A language is a tag such as \"fr\" or \"fr-CA\"; \"fr\n\" is not."],
            [static fn (): SearchQuery => SearchQuery::of('x', language: 'f'), 'A language is a tag such as "fr" or "fr-CA"; "f" is not.'],
            [static fn (): SearchQuery => SearchQuery::of('x')->withTerms([['', ' ']]), 'A term has 1 to 32 alternatives; 0 given.'],
            [static fn (): SearchQuery => SearchQuery::of('x')->withTerms([array_map(static fn (int $n): string => 'w'.$n, range(0, 32))]), 'A term has 1 to 32 alternatives; 33 given.'],
        ] as [$call, $message]) {
            try {
                $call();
                self::fail($message);
            } catch (InvalidSearch $invalidSearch) {
                self::assertSame($message, $invalidSearch->getMessage());
            }
        }
    }

    public function testFacetsAreSortedByCountThenValueAndCapped(): void
    {
        $counts = ['b' => 3, 'a' => 3, 2026 => 5, 'none' => 0, 'z' => 1];

        foreach (range(1, 120) as $n) {
            $counts['v'.$n] = 1;
        }

        $result = new SearchResult(['x'], 1, ['genre' => $counts, 'year' => []]);
        $genre = $result->facet('genre');

        self::assertCount(SearchResult::MAX_FACET_VALUES, $genre);
        self::assertEquals([new FacetValue('2026', 5), new FacetValue('a', 3), new FacetValue('b', 3), new FacetValue('v1', 1)], \array_slice($genre, 0, 4));
        self::assertSame('2026', $genre[0]->value);
        self::assertSame([], $result->facet('year'));
        self::assertSame([], $result->facet('unknown'));
        self::assertSame(['genre', 'year'], array_keys($result->facets));
        self::assertSame([], new SearchResult([], 0)->facets);
        self::assertSame(['a', 'b'], array_map(static fn (FacetValue $value): string => $value->value, new SearchResult([], 0, ['f' => ['b' => 1, 'a' => 1]])->facet('f')));
    }

    public function testPhrasesAreComparedInLowerCaseWithOneSpace(): void
    {
        self::assertSame('jeu de rôle', Phrase::normalize("  Jeu\n\tde  RÔLE "));
        self::assertSame(['jeu', 'de', 'rôle'], Phrase::words(' Jeu de  rôle'));
        self::assertSame([], Phrase::words(" \n "));
        self::assertSame('?', Phrase::normalize("\xFF"));
    }

    public function testSynonymRules(): void
    {
        $equivalent = SynonymRule::equivalent(['JDR', 'jeu  de rôle', 'jdr']);
        self::assertSame([['jdr', 'jeu de rôle'], ['jdr', 'jeu de rôle'], true], [$equivalent->from, $equivalent->to, $equivalent->equivalent]);

        $oneWay = SynonymRule::oneWay(['Donjon'], ['donjon', 'Dungeon']);
        self::assertSame([['donjon'], ['donjon', 'dungeon'], false], [$oneWay->from, $oneWay->to, $oneWay->equivalent]);
        self::assertSame(['a b c d e f g h'], SynonymRule::oneWay(['a b c d e f g h'], ['x'])->from);
        self::assertSame([str_repeat('é', 100)], SynonymRule::oneWay([str_repeat('É', 100)], ['x'])->from);
        self::assertCount(64, SynonymRule::equivalent(array_map(static fn (int $n): string => 'w'.$n, range(1, 64)))->from);
        self::assertSame(['a'], SynonymRule::oneWay(['a', 'b'], ['a'])->to);

        foreach ([
            [static fn (): SynonymRule => SynonymRule::equivalent(['jdr', 'JDR']), 'Equivalent synonyms are two phrases or more.'],
            [static fn (): SynonymRule => SynonymRule::oneWay([], ['x']), 'One-way synonyms are one phrase or more on each side.'],
            [static fn (): SynonymRule => SynonymRule::oneWay(['x'], []), 'One-way synonyms are one phrase or more on each side.'],
            [static fn (): SynonymRule => SynonymRule::oneWay(['a', 'b'], ['B', 'a']), 'One-way synonyms change something: the two sides are the same.'],
            [static fn (): SynonymRule => SynonymRule::equivalent(['a', ' ']), 'A synonym is 1 to 100 characters and 8 words at most; " " is not.'],
            [static fn (): SynonymRule => SynonymRule::equivalent(['a', str_repeat('b', 101)]), 'A synonym is 1 to 100 characters and 8 words at most; "'.str_repeat('b', 101).'" is not.'],
            [static fn (): SynonymRule => SynonymRule::equivalent(['a', 'a b c d e f g h i']), 'A synonym is 1 to 100 characters and 8 words at most; "a b c d e f g h i" is not.'],
            [static fn (): SynonymRule => SynonymRule::equivalent(array_map(static fn (int $n): string => 'w'.$n, range(0, 64))), 'A synonym rule holds 64 phrases at most; 65 given.'],
        ] as [$call, $message]) {
            try {
                $call();
                self::fail($message);
            } catch (InvalidSearch $invalidSearch) {
                self::assertSame($message, $invalidSearch->getMessage());
            }
        }
    }

    public function testFacetsAndAlternativesStayListsWhateverIsLeftOut(): void
    {
        self::assertSame(['genre', 'year'], SearchQuery::of('', facets: ['genre', 'genre', 'year'])->facets);
        self::assertSame([['a', 'b']], SearchQuery::of('x')->withTerms([['', 'A', 'a', 'b']])->terms());
    }

    public function testAFacetValueCountedZeroTimesIsLeftOut(): void
    {
        $result = new SearchResult([], 0, ['f' => ['a' => 1, 'zero' => 0]]);

        self::assertEquals([new FacetValue('a', 1)], $result->facet('f'));
    }
}
