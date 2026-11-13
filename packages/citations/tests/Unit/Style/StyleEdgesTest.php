<?php

declare(strict_types=1);

namespace Phil\Citations\Tests\Unit\Style;

use Phil\Citations\Citation;
use Phil\Citations\CitationProcessor;
use Phil\Citations\Cite;
use Phil\Citations\Contract\CitationStyle;
use Phil\Citations\CslJson;
use Phil\Citations\ReferenceList;
use Phil\Citations\RichText;
use Phil\Citations\Style\AbstractStyle;
use Phil\Citations\Style\AuthorDate;
use Phil\Citations\Style\BibliographyOrder;
use Phil\Citations\Style\Notes;
use Phil\Citations\Style\Numeric;
use Phil\Citations\Terms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractStyle::class)]
#[CoversClass(AuthorDate::class)]
#[CoversClass(Notes::class)]
#[CoversClass(Numeric::class)]
#[CoversClass(CitationProcessor::class)]
final class StyleEdgesTest extends TestCase
{
    private const string JSON = <<<'JSON'
        [
            {"id": "a1", "type": "book", "title": "One", "author": [{"family": "Ng"}], "issued": {"date-parts": [[2001]]}},
            {"id": "a2", "type": "book", "title": "Two", "author": [{"family": "Ng"}], "issued": {"date-parts": [[2002]]}},
            {"id": "nt", "type": "book", "author": [{"family": "Ng"}]},
            {"id": "ne", "type": "book", "author": [{"family": "Ng"}], "edition": "2", "genre": "Draft"},
            {"id": "ch", "type": "chapter", "title": "Chap", "author": [{"family": "Ng"}], "editor": [{"family": "Doe", "given": "Jane"}],
             "container-title": "Coll", "publisher": "P", "publisher-place": "Paris"},
            {"id": "bk", "type": "book", "title": "Bk", "author": [{"family": "Ng"}], "number": "7", "container-title": "CT", "publisher": "Pub"},
            {"id": "rp", "type": "report", "title": "Rp", "author": [{"family": "Ng"}], "publisher": "Pub"},
            {"id": "wp", "type": "webpage", "title": "Wp", "author": [{"family": "Ng"}], "publisher": "Pub"},
            {"id": "pl", "type": "book", "title": "Pl", "author": [{"family": "Ng"}], "publisher-place": "Paris", "issued": {"date-parts": [[2005]]}},
            {"id": "ar", "type": "article-journal", "title": "Ar", "author": [{"family": "Ng"}], "container-title": "J", "issued": {"date-parts": [[2006, 3]]}},
            {"id": "t3", "type": "book", "title": "T3", "author": [{"family": "A1"}, {"family": "A2"}, {"family": "A3"}]},
            {"id": "t4", "type": "book", "title": "T4", "author": [{"family": "A1"}, {"family": "A2"}, {"family": "A3"}, {"family": "A4"}]},
            {"id": "t6", "type": "book", "title": "T6", "author": [{"family": "A1"}, {"family": "A2"}, {"family": "A3"}, {"family": "A4"}, {"family": "A5"}, {"family": "A6"}]},
            {"id": "t7", "type": "book", "title": "T7", "author": [{"family": "A1"}, {"family": "A2"}, {"family": "A3"}, {"family": "A4"}, {"family": "A5"}, {"family": "A6"}, {"family": "A7"}]}
        ]
        JSON;

    /**
     * @param list<Citation> $citations
     *
     * @return array{list<string>, list<string>}
     */
    private function render(CitationStyle $style, array $citations, ?Terms $terms = null, string $json = self::JSON): array
    {
        $rendered = new CitationProcessor($style, $terms ?? Terms::english())->render(new ReferenceList(new CslJson()->decode($json)), $citations);
        $text = static fn (RichText $text): string => $text->toText();

        return [array_map($text, $rendered->citations), array_map($text, $rendered->bibliography)];
    }

    /**
     * @param list<int> $counts
     */
    private function authors(array $counts): string
    {
        $items = [];

        foreach ($counts as $count) {
            $names = array_map(static fn (int $number): array => ['family' => 'A'.$number], range(1, $count));
            $items[] = ['id' => 't'.$count, 'type' => 'book', 'title' => 'T'.$count, 'author' => $names];
        }

        return json_encode($items, \JSON_THROW_ON_ERROR);
    }

    public function testTheStylesThatNeedNoYearLetters(): void
    {
        self::assertTrue(new AuthorDate()->yearSuffixes());
        self::assertFalse(new Notes()->yearSuffixes());
        self::assertFalse(new Numeric()->yearSuffixes());
        self::assertSame(BibliographyOrder::Citation, new Numeric()->order());
    }

    public function testAuthorDateGroupsOnlyThePlainCitesOfOneAuthor(): void
    {
        [$citations] = $this->render(new AuthorDate(), [
            new Citation(new Cite('a1'), new Cite('a2', prefix: 'see')),
            new Citation(new Cite('a1'), new Cite('a2', suffix: 'ok')),
            new Citation(new Cite('a1'), new Cite('a2')),
        ]);

        self::assertSame(['(Ng, 2001; see Ng, 2002)', '(Ng, 2001; Ng, 2002 ok)', '(Ng, 2001, 2002)'], $citations);
    }

    public function testAuthorDateLeavesOutTheDetailsOfAnUntitledReference(): void
    {
        [, $bibliography] = $this->render(new AuthorDate(), [Citation::of('ne')]);

        self::assertSame(['Ng. (n.d.).'], $bibliography);
    }

    public function testAuthorDateEntries(): void
    {
        [, $bibliography] = $this->render(new AuthorDate(), [Citation::of('nt', 'ch', 'bk', 'rp', 'wp', 'pl', 'ar')]);

        self::assertSame([
            'Ng. (2005). Pl.',
            'Ng. (2006). Ar. J.',
            'Ng. (n.d.-a).',
            'Ng. (n.d.-b). Bk. Pub.',
            'Ng. (n.d.-c). Chap. In J. Doe (Ed.), Coll. P.',
            'Ng. (n.d.-d). Rp. Pub.',
            'Ng. (n.d.-e). Wp. Pub.',
        ], $bibliography);
    }

    public function testAuthorDateNamesTwentyAuthorsInFullAndMoreWithAnEllipsis(): void
    {
        [, $bibliography] = $this->render(new AuthorDate(), [Citation::of('t20', 't21')], json: $this->authors([20, 21]));

        self::assertStringContainsString('A18, A19, & A20. (n.d.). T20.', $bibliography[0]);
        self::assertStringContainsString('A18, A19, . . . A21. (n.d.). T21.', $bibliography[1]);
    }

    public function testNotesNameTenAuthorsInFullAndMoreAsEtAl(): void
    {
        [, $bibliography] = $this->render(new Notes(), [Citation::of('t10', 't11')], json: $this->authors([10, 11]));

        self::assertStringEndsWith('A8, A9, and A10. T10.', $bibliography[0]);
        self::assertSame('A1, A2, A3, A4, A5, A6, A7, et al. T11.', $bibliography[1]);
    }

    public function testNotesWriteALocatorWithoutItsSpaces(): void
    {
        [$citations, $bibliography] = $this->render(new Notes(), [
            new Citation(new Cite('a1', ' 12 ')),
            Citation::of('pl', 'ar'),
        ]);

        self::assertSame('Ng, One (2001), 12.', $citations[0]);
        self::assertSame(['Ng. One. 2001.', 'Ng. Pl. Paris, 2005.', 'Ng. “Ar.” J (March 2006).'], $bibliography);
    }

    public function testNotesAndNumericNameThreeAndSixAuthorsInFull(): void
    {
        [$notes] = $this->render(new Notes(), [Citation::of('t3', 't4')]);
        [, $numeric] = $this->render(new Numeric(), [Citation::of('t6', 't7')]);

        self::assertSame('A1, A2, and A3, T3; A1 et al., T4.', $notes[0]);
        self::assertSame(['[1] A1, A2, A3, A4, A5, and A6, T6.', '[2] A1 et al., T7.'], $numeric);
    }

    public function testNumericEntries(): void
    {
        [, $bibliography] = $this->render(new Numeric(), [Citation::of('pl', 'ch', 'ar')]);

        self::assertSame([
            '[1] Ng, Pl. Paris, 2005.',
            '[2] Ng, “Chap,” in Coll, J. Doe, Ed. Paris: P.',
            '[3] Ng, “Ar,” J, Mar. 2006.',
        ], $bibliography);
    }

    public function testNumericSortsItsCites(): void
    {
        [$citations] = $this->render(new Numeric(), [Citation::of('a1'), Citation::of('a2', 'a1')]);

        self::assertSame(['[1]', '[1], [2]'], $citations);
    }

    public function testNumericKeepsALocatorOutOfARun(): void
    {
        [$citations] = $this->render(new Numeric(), [
            new Citation(new Cite('a1'), new Cite('a2', '5')),
            new Citation(new Cite('a1', prefix: 'see'), new Cite('a2')),
        ]);

        self::assertSame(['[1], [2, p. 5]', 'see [1], [2]'], $citations);
    }

    public function testTheFirstCiteOfAReferenceWithinOneCitationWins(): void
    {
        [$citations] = $this->render(new Numeric(), [new Citation(new Cite('a1', '1'), new Cite('a1', '2'))]);

        self::assertSame(['[1, p. 1]'], $citations);
    }

    public function testNumericAbbreviatesMonthsByTheirLetters(): void
    {
        $terms = Fixtures::englishWith(months: ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre']);
        $json = '[
            {"id": "x", "type": "article-journal", "title": "X", "container-title": "J", "issued": {"date-parts": [[2006, 8]]}},
            {"id": "y", "type": "article-journal", "title": "Y", "container-title": "J", "issued": {"date-parts": [[2006, 2]]}}
        ]';

        [, $bibliography] = $this->render(new Numeric(), [Citation::of('x', 'y')], $terms, $json);

        self::assertSame(['[1] “X,” J, Août 2006.', '[2] “Y,” J, Fév. 2006.'], $bibliography);
    }

    public function testAuthorDateWritesOneEditorOfAChapterInTheSingular(): void
    {
        [, $bibliography] = $this->render(new AuthorDate(), [Citation::of('ch')]);

        self::assertStringContainsString('J. Doe (Ed.)', $bibliography[0]);
    }

    /**
     * @return iterable<string, array{CitationStyle, string}>
     */
    public static function theBlankReference(): iterable
    {
        yield 'author-date' => [new AuthorDate(), 'Ng. (n.d.).'];
        yield 'notes' => [new Notes(), 'Ng.'];
        yield 'numeric' => [new Numeric(), '[1] Ng.'];
    }

    #[DataProvider('theBlankReference')]
    public function testAReferenceWithoutATitle(CitationStyle $style, string $expected): void
    {
        [, $bibliography] = $this->render($style, [Citation::of('nt')]);

        self::assertSame([$expected], $bibliography);
    }

    public function testCreatorsAndYearsDoNotShareALetterByChance(): void
    {
        $json = '[
            {"id": "p", "type": "book", "title": "P", "author": [{"family": "Ng", "given": "20"}], "issued": {"date-parts": [[1]]}},
            {"id": "q", "type": "book", "title": "Q", "author": [{"family": "Ng"}], "issued": {"date-parts": [[201]]}},
            {"id": "r", "type": "book", "title": "R", "author": [{"family": "Ng", "given": "A"}], "issued": {"date-parts": [[2001]]}},
            {"id": "s", "type": "book", "title": "S", "author": [{"family": "NgA"}], "issued": {"date-parts": [[2001]]}}
        ]';

        [, $bibliography] = $this->render(new AuthorDate(), [Citation::of('p', 'q', 'r', 's')], json: $json);

        self::assertSame(['Ng. (201). Q.', 'Ng, 2. (1). P.', 'Ng, A. (2001). R.', 'NgA. (2001). S.'], $bibliography);
    }
}
