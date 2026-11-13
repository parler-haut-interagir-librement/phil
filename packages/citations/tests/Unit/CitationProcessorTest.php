<?php

declare(strict_types=1);

namespace Phil\Citations\Tests\Unit;

use Phil\Citations\Citation;
use Phil\Citations\CitationProcessor;
use Phil\Citations\Contract\CitationStyle;
use Phil\Citations\CslJson;
use Phil\Citations\Exception\UnknownReference;
use Phil\Citations\ReferenceList;
use Phil\Citations\RenderedCitations;
use Phil\Citations\RichText;
use Phil\Citations\Style\AbstractStyle;
use Phil\Citations\Style\AuthorDate;
use Phil\Citations\Style\BibliographyOrder;
use Phil\Citations\Style\CitedItem;
use Phil\Citations\Style\Entry;
use Phil\Citations\Style\Notes;
use Phil\Citations\Style\Numeric;
use Phil\Citations\Terms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CitationProcessor::class)]
#[CoversClass(RenderedCitations::class)]
#[CoversClass(AbstractStyle::class)]
#[CoversClass(AuthorDate::class)]
#[CoversClass(Notes::class)]
#[CoversClass(Numeric::class)]
#[CoversClass(Entry::class)]
#[CoversClass(CitedItem::class)]
final class CitationProcessorTest extends TestCase
{
    private const string JSON = <<<'JSON'
        [
            {"id": "a1", "type": "article-journal", "title": "Bare Article", "author": [{"family": "Ng"}], "container-title": "J"},
            {"id": "a2", "type": "article-journal", "title": "Issue Only", "author": [{"family": "Ng"}], "container-title": "J", "issue": "4",
             "issued": {"date-parts": [[2001]]}},
            {"id": "c1", "type": "chapter", "title": "Bare Chapter", "author": [{"family": "Ng"}], "container-title": "Book"},
            {"id": "w1", "type": "webpage", "title": "Bare Page"},
            {"id": "b1", "type": "book", "title": "Revised", "edition": "Revised", "editor": [{"family": "Ed"}],
             "author": [{"family": "Ng", "given": "Al", "suffix": "Jr.", "dropping-particle": "de"}]},
            {"id": "dup", "type": "book", "title": "Revised", "author": [{"family": "Ng", "given": "Al", "suffix": "Jr.", "dropping-particle": "de"}]},
            {"id": "uncited", "type": "book", "title": "Never Cited", "author": [{"family": "Aa"}]}
        ]
        JSON;

    /**
     * @return iterable<string, array{CitationStyle, list<string>, list<string>}>
     */
    public static function edges(): iterable
    {
        yield 'author-date' => [new AuthorDate(), [
            '(Ng, n.d.-a, 2001, n.d.-b; <i>Bare Page</i>, n.d.; Ng, n.d.)',
            '(Ng, n.d.)',
            '(Ng, n.d.-a)',
        ], [
            'Aa. (n.d.). <i>Never Cited</i>.',
            '<i>Bare Page</i>. (n.d.).',
            'Ng. (2001). Issue Only. <i>J</i>, (4).',
            'Ng. (n.d.-a). Bare Article. <i>J</i>.',
            'Ng. (n.d.-b). Bare Chapter. In <i>Book</i>.',
            'Ng, A. de, Jr. (n.d.). <i>Revised</i> (Revised ed.).',
        ]];
        yield 'notes' => [new Notes(), [
            'Ng, “Bare Article,” <i>J</i>; Ng, “Issue Only,” <i>J</i> no. 4 (2001); Ng, “Bare Chapter,” in <i>Book</i>; “Bare Page”; Al de Ng Jr., <i>Revised</i>, ed. Ed, Revised ed.',
            'Ng, <i>Revised</i>.',
            'Ng, “Bare Article.”',
        ], [
            'Aa. <i>Never Cited</i>.',
            '“Bare Page.”',
            'Ng. “Issue Only.” <i>J</i> no. 4 (2001).',
            'Ng. “Bare Article.” <i>J</i>.',
            'Ng. “Bare Chapter.” In <i>Book</i>.',
            'Ng, Al de, Jr. <i>Revised</i>. Edited by Ed. Revised ed.',
        ]];
        yield 'numeric' => [new Numeric(), ['[1]–[5]', '[5]', '[1]'], [
            '[1] Ng, “Bare Article,” <i>J</i>.',
            '[2] Ng, “Issue Only,” <i>J</i>, no. 4, 2001.',
            '[3] Ng, “Bare Chapter,” in <i>Book</i>.',
            '[4] “Bare Page.”',
            '[5] A. de Ng Jr., <i>Revised</i>, Revised ed.',
            '[6] Aa, <i>Never Cited</i>.',
        ]];
    }

    /**
     * @param list<string> $citations
     * @param list<string> $bibliography
     */
    #[DataProvider('edges')]
    public function testEdgesInHtmlWithTheUncited(CitationStyle $style, array $citations, array $bibliography): void
    {
        $rendered = new CitationProcessor($style, Terms::english())->render(
            new ReferenceList(new CslJson()->decode(self::JSON)),
            [Citation::of('a1', 'a2', 'c1', 'w1', 'b1'), Citation::of('dup'), Citation::of('a1')],
            uncited: true,
        );

        self::assertSame($citations, array_map(static fn (RichText $text): string => $text->toHtml(), $rendered->citations));
        self::assertSame($bibliography, array_map(static fn (RichText $text): string => $text->toHtml(), $rendered->bibliography));
    }

    public function testOnlyTheCitedAreListedByDefault(): void
    {
        $rendered = new CitationProcessor(new Numeric(), Terms::english())->render(new ReferenceList(new CslJson()->decode(self::JSON)), [Citation::of('w1')]);

        self::assertSame(['[1] “Bare Page.”'], array_map(static fn (RichText $text): string => $text->toText(), $rendered->bibliography));
    }

    public function testNothingCitedGivesNothing(): void
    {
        $rendered = new CitationProcessor(new AuthorDate(), Terms::english())->render(new ReferenceList([]), []);

        self::assertSame([], $rendered->citations);
        self::assertSame([], $rendered->bibliography);
    }

    public function testAnUnknownIdIsRefused(): void
    {
        $this->expectExceptionObject(UnknownReference::id('nope'));

        new CitationProcessor(new AuthorDate(), Terms::english())->render(new ReferenceList([]), [Citation::of('nope')]);
    }

    public function testAStyleOfItsOwn(): void
    {
        $style = new class implements CitationStyle {
            public function order(): BibliographyOrder
            {
                return BibliographyOrder::Alphabetical;
            }

            public function yearSuffixes(): bool
            {
                return true;
            }

            public function citation(array $items, Terms $terms): RichText
            {
                return RichText::text(implode(' ', array_map(static fn (CitedItem $item): string => $item->entry->reference->id.($item->first ? '*' : '').$item->entry->yearSuffix, $items)));
            }

            public function entry(Entry $entry, Terms $terms): RichText
            {
                return RichText::text($entry->number.'. '.$entry->reference->id.$entry->yearSuffix);
            }
        };

        $rendered = new CitationProcessor($style, Terms::french())->render(
            new ReferenceList(new CslJson()->decode(self::JSON)),
            [Citation::of('uncited', 'c1'), Citation::of('c1', 'a1')],
        );

        self::assertSame(['uncited* c1*b', 'c1b a1*a'], array_map(static fn (RichText $text): string => $text->toText(), $rendered->citations));
        self::assertSame(['1. uncited', '2. a1a', '3. c1b'], array_map(static fn (RichText $text): string => $text->toText(), $rendered->bibliography));
    }

    public function testTheCollationOfTheLanguage(): void
    {
        $references = new CslJson()->decode('[
            {"id": "z", "type": "book", "title": "T", "author": [{"family": "Zola"}]},
            {"id": "e", "type": "book", "title": "T", "author": [{"family": "Émond"}]},
            {"id": "a", "type": "book", "title": "T", "author": [{"family": "Abel"}]},
            {"id": "f", "type": "book", "title": "T", "author": [{"family": "Ferry"}]}
        ]');

        $rendered = new CitationProcessor(new AuthorDate(), Terms::french())->render(new ReferenceList($references), [Citation::of('z', 'e', 'a', 'f')]);

        self::assertSame(['Abel. (s. d.). <i>T</i>.', 'Émond. (s. d.). <i>T</i>.', 'Ferry. (s. d.). <i>T</i>.', 'Zola. (s. d.). <i>T</i>.'], array_map(static fn (RichText $text): string => $text->toHtml(), $rendered->bibliography));
    }

    public function testTiesAreOrderedByYearThenTitleThenId(): void
    {
        $references = new CslJson()->decode('[
            {"id": "b", "type": "book", "title": "Same", "author": [{"family": "Lee"}], "issued": {"date-parts": [[2001]]}, "DOI": "10.1/b"},
            {"id": "a", "type": "book", "title": "Same", "author": [{"family": "Lee"}], "issued": {"date-parts": [[2001]]}, "DOI": "10.1/a"},
            {"id": "y", "type": "book", "title": "Zeta", "author": [{"family": "Lee"}], "issued": {"date-parts": [[2000]]}},
            {"id": "t", "type": "book", "title": "Alpha", "author": [{"family": "Lee"}], "issued": {"date-parts": [[2001]]}}
        ]');

        $rendered = new CitationProcessor(new Numeric(), Terms::english())->render(new ReferenceList($references), [Citation::of('b', 'a', 'y', 't')]);
        $sorted = new CitationProcessor(new Notes(), Terms::english())->render(new ReferenceList($references), [Citation::of('b', 'a', 'y', 't')]);

        self::assertSame('[1]–[4]', $rendered->citations[0]->toText());
        self::assertSame(['Lee. <i>Zeta</i>. 2000.', 'Lee. <i>Alpha</i>. 2001.', 'Lee. <i>Same</i>. 2001. https://doi.org/10.1/a.', 'Lee. <i>Same</i>. 2001. https://doi.org/10.1/b.'], array_map(static fn (RichText $text): string => $text->toHtml(), $sorted->bibliography));
    }
}
