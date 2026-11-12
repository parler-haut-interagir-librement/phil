<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Unit\Domain;

use Phil\SearchBundle\Domain\Exception\InvalidSearch;
use Phil\SearchBundle\Domain\Exception\SearchException;
use Phil\SearchBundle\Domain\ValueObject\Document;
use Phil\SearchBundle\Domain\ValueObject\Index;
use Phil\SearchBundle\Domain\ValueObject\SearchQuery;
use Phil\SearchBundle\Domain\ValueObject\SearchResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Document::class)]
#[CoversClass(Index::class)]
#[CoversClass(SearchQuery::class)]
#[CoversClass(SearchResult::class)]
#[CoversClass(InvalidSearch::class)]
final class ValuesTest extends TestCase
{
    public function testADocumentAndAQuery(): void
    {
        $document = Document::of('articles_fr', 'A-1_b', ['title' => 'Bonjour'], ['locale' => 'fr', 'tags' => ['a', 'b']]);
        self::assertSame(['articles_fr', 'A-1_b', ['title' => 'Bonjour'], ['locale' => 'fr', 'tags' => ['a', 'b']]], [$document->index, $document->id, $document->text, $document->filters]);
        self::assertSame([], Document::of('a', str_repeat('1', 255), [])->filters);
        self::assertSame('a'.str_repeat('b', 63), Index::check('a'.str_repeat('b', 63)));

        $query = SearchQuery::of('  Jeux  de  JEUX rôle ', ['locale' => 'fr'], 1000, 40);
        self::assertSame(['Jeux  de  JEUX rôle', ['locale' => 'fr'], 1000, 40], [$query->text, $query->filters, $query->limit, $query->offset]);
        self::assertSame(['jeux', 'de', 'rôle'], $query->words());
        self::assertSame([[], 20, 0], [SearchQuery::of('')->words(), SearchQuery::of('')->limit, SearchQuery::of('')->offset]);
        self::assertSame(1, SearchQuery::of('x', [], 1)->limit);
        self::assertSame(['a', 'b', 'c'], SearchQuery::of("a\tb\u{2003}c")->words());
        self::assertSame(['?'], SearchQuery::of("\xFF")->words());

        $result = new SearchResult(['a', 'b'], 7);
        self::assertSame([['a', 'b'], 7], [$result->ids, $result->total]);
    }

    public function testBadValuesAreRefused(): void
    {
        foreach ([
            [static fn (): string => Index::check('Articles'), 'An index is 1 to 64 lower-case letters, digits or "_", starting with a letter; "Articles" is not.'],
            [static fn (): string => Index::check('1a'), 'An index is 1 to 64 lower-case letters, digits or "_", starting with a letter; "1a" is not.'],
            [static fn (): string => Index::check("a\n"), "An index is 1 to 64 lower-case letters, digits or \"_\", starting with a letter; \"a\n\" is not."],
            [static fn (): string => Index::check('a'.str_repeat('b', 64)), 'An index is 1 to 64 lower-case letters, digits or "_", starting with a letter; "a'.str_repeat('b', 64).'" is not.'],
            [static fn (): Document => Document::of('a', 'a b', []), 'A document identifier is 1 to 255 letters, digits, "_" or "-"; "a b" is not.'],
            [static fn (): Document => Document::of('a', '', []), 'A document identifier is 1 to 255 letters, digits, "_" or "-"; "" is not.'],
            [static fn (): Document => Document::of('a', "a\n", []), "A document identifier is 1 to 255 letters, digits, \"_\" or \"-\"; \"a\n\" is not."],
            [static fn (): Document => Document::of('a', str_repeat('1', 256), []), 'A document identifier is 1 to 255 letters, digits, "_" or "-"; "'.str_repeat('1', 256).'" is not.'],
            [static fn (): Document => Document::of('A', '1', []), 'An index is 1 to 64 lower-case letters, digits or "_", starting with a letter; "A" is not.'],
            [static fn (): Document => Document::of('a', '1', ['Title' => 'x']), 'A field is 1 to 64 lower-case letters, digits or "_", starting with a letter; "Title" is not.'],
            [static fn (): Document => Document::of('a', '1', [], ['1tag' => 'x']), 'A field is 1 to 64 lower-case letters, digits or "_", starting with a letter; "1tag" is not.'],
            [static fn (): SearchQuery => SearchQuery::of('x', [], 0), 'A page of results holds 1 to 1000 documents; 0 asked.'],
            [static fn (): SearchQuery => SearchQuery::of('x', [], 1001), 'A page of results holds 1 to 1000 documents; 1001 asked.'],
            [static fn (): SearchQuery => SearchQuery::of('x', [], 20, -1), 'A page starts at 0 or after; -1 asked.'],
            [static fn (): SearchQuery => SearchQuery::of('x', ['Locale' => 'fr']), 'A field is 1 to 64 lower-case letters, digits or "_", starting with a letter; "Locale" is not.'],
            [static fn (): SearchQuery => SearchQuery::of('x', ["a\n" => 'fr']), "A field is 1 to 64 lower-case letters, digits or \"_\", starting with a letter; \"a\n\" is not."],
            [static fn (): Document => Document::of('a', '1', ["a\n" => 'x']), "A field is 1 to 64 lower-case letters, digits or \"_\", starting with a letter; \"a\n\" is not."],
        ] as [$make, $message]) {
            try {
                $make();
                self::fail($message);
            } catch (InvalidSearch $invalidSearch) {
                self::assertSame($message, $invalidSearch->getMessage());
                self::assertInstanceOf(SearchException::class, $invalidSearch);
            }
        }
    }
}
