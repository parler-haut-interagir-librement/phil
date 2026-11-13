<?php

declare(strict_types=1);

namespace Phil\Citations\Tests\Unit;

use Phil\Citations\Citation;
use Phil\Citations\Cite;
use Phil\Citations\Exception\MissingTerms;
use Phil\Citations\Locator;
use Phil\Citations\RichText;
use Phil\Citations\Terms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RichText::class)]
#[CoversClass(Terms::class)]
#[CoversClass(Cite::class)]
#[CoversClass(Citation::class)]
#[CoversClass(MissingTerms::class)]
final class TextTest extends TestCase
{
    public function testRichText(): void
    {
        $text = RichText::concat('Smith, ', RichText::italic('Title <b>'), RichText::empty(), ' & co');

        self::assertSame('Smith, Title <b> & co', $text->toText());
        self::assertSame('Smith, <i>Title &lt;b&gt;</i> &amp; co', $text->toHtml());
        self::assertFalse($text->isEmpty());
        self::assertTrue(RichText::text('')->isEmpty());
        self::assertTrue(RichText::italic('')->isEmpty());
        self::assertSame("&apos;\u{FFFD}", RichText::text("'\xFF")->toHtml());
    }

    public function testJoin(): void
    {
        self::assertSame('a, b, c', RichText::join(', ', ['a', '', RichText::empty(), RichText::text('b'), 'c'])->toText());
        self::assertSame('', RichText::join(', ', [])->toText());
        self::assertSame('<i>a</i>; b', RichText::join('; ', [RichText::italic('a'), 'b'])->toHtml());
    }

    /**
     * @return iterable<string, array{string, string, string, bool, string}>
     */
    public static function punctuations(): iterable
    {
        yield 'a period added' => ['Title', '.', '', false, 'Title.'];
        yield 'a period kept' => ['Ed.', '.', '', false, 'Ed.'];
        yield 'a question mark ends' => ['Why?', '.', '', false, 'Why?'];
        yield 'an exclamation mark ends' => ['Now!', '.', '', false, 'Now!'];
        yield 'a comma after a period' => ['Ed.', ',', '', false, 'Ed.,'];
        yield 'no comma after a question mark' => ['Why?', ',', '', false, 'Why?'];
        yield 'no comma after an exclamation mark' => ['Now!', ',', '', false, 'Now!'];
        yield 'no comma after a quoted question' => ['“Why?”', ',', '”', true, '“Why?”'];
        yield 'a comma kept' => ['a,', ',', '', false, 'a,'];
        yield 'inside quotes' => ['“Title”', ',', '”', true, '“Title,”'];
        yield 'outside quotes' => ['« Titre »', ',', ' »', false, '« Titre »,'];
        yield 'a period inside quotes' => ['“Title”', '.', '”', true, '“Title.”'];
        yield 'a question inside quotes' => ['“Why?”', '.', '”', true, '“Why?”'];
        yield 'no quote at the end' => ['Title', ',', '”', true, 'Title,'];
        yield 'empty' => ['', '.', '', false, ''];
    }

    #[DataProvider('punctuations')]
    public function testPunctuate(string $text, string $mark, string $closeQuote, bool $inside, string $expected): void
    {
        self::assertSame($expected, RichText::text($text)->punctuate($mark, $closeQuote, $inside)->toText());
    }

    public function testPunctuationKeepsItalics(): void
    {
        self::assertSame('a <i>“b,”</i>', RichText::concat('a ', RichText::italic('“b”'))->punctuate(',', '”', true)->toHtml());
        self::assertSame('<i>b</i>.', RichText::italic('b')->sentence()->toHtml());
        self::assertSame('“b.”', RichText::text('“b”')->sentence('”', true)->toText());
    }

    public function testEnglishTerms(): void
    {
        $terms = Terms::english();

        self::assertSame('en', $terms->language);
        self::assertSame('1st', $terms->ordinal(1));
        self::assertSame('2nd', $terms->ordinal(2));
        self::assertSame('3rd', $terms->ordinal(3));
        self::assertSame('4th', $terms->ordinal(4));
        self::assertSame('11th', $terms->ordinal(11));
        self::assertSame('12th', $terms->ordinal(12));
        self::assertSame('13th', $terms->ordinal(13));
        self::assertSame('21st', $terms->ordinal(21));
        self::assertSame('102nd', $terms->ordinal(102));
        self::assertSame('111th', $terms->ordinal(111));
        self::assertSame('p.', $terms->locatorLabel(Locator::Page, '12'));
        self::assertSame('pp.', $terms->locatorLabel(Locator::Page, '12-14'));
        self::assertSame('pp.', $terms->locatorLabel(Locator::Page, '12–14'));
        self::assertSame('pp.', $terms->locatorLabel(Locator::Page, '12, 14'));
        self::assertSame('pp.', $terms->locatorLabel(Locator::Page, '12 & 14'));
        self::assertSame('chap.', $terms->locatorLabel(Locator::Chapter, '3'));
        self::assertSame('secs.', $terms->locatorLabel(Locator::Section, '1-2'));
        self::assertSame('para.', $terms->locatorLabel(Locator::Paragraph, '4'));
        self::assertSame('vol.', $terms->locatorLabel(Locator::Volume, '2'));
        self::assertSame('fig.', $terms->locatorLabel(Locator::Figure, '2'));
        self::assertSame('ll.', $terms->locatorLabel(Locator::Line, '2-3'));
        self::assertSame('n.', $terms->locatorLabel(Locator::Note, '2'));
        self::assertSame('January', $terms->month(1));
        self::assertSame('December', $terms->month(12));
        self::assertSame('', $terms->month(13));
        self::assertSame('', $terms->month(0));
    }

    public function testFrenchTerms(): void
    {
        $terms = Terms::french();

        self::assertSame('fr', $terms->language);
        self::assertSame('1re', $terms->ordinal(1));
        self::assertSame('2e', $terms->ordinal(2));
        self::assertSame('21e', $terms->ordinal(21));
        self::assertSame('11e', $terms->ordinal(11));
        self::assertSame('p.', $terms->locatorLabel(Locator::Page, '12-14'));
        self::assertSame('janvier', $terms->month(1));
        self::assertSame("«\u{00A0}", $terms->openQuote);
        self::assertFalse($terms->punctuationInsideQuotes);
    }

    public function testATermsWithoutSuffixesOrLabels(): void
    {
        $english = Terms::english();
        $terms = new Terms(
            $english->language,
            $english->and,
            $english->andSymbol,
            $english->serialComma,
            $english->etAl,
            $english->noDate,
            $english->editor,
            $english->editors,
            $english->editedBy,
            $english->translatedBy,
            $english->in,
            $english->edition,
            $english->volume,
            $english->issue,
            $english->pages,
            $english->accessed,
            $english->online,
            $english->available,
            $english->openQuote,
            $english->closeQuote,
            $english->punctuationInsideQuotes,
            $english->colon,
            $english->capitalizeAbbreviations,
            $english->beforeCommonEra,
            $english->months,
            [],
            [],
        );

        self::assertSame('3', $terms->ordinal(3));
        self::assertSame('', $terms->locatorLabel(Locator::Page, '1'));
    }

    public function testTermsByLocale(): void
    {
        self::assertSame('en', Terms::forLocale('en_US')->language);
        self::assertSame('fr', Terms::forLocale('FR-ca')->language);

        $this->expectExceptionObject(MissingTerms::forLocale('de'));

        Terms::forLocale('de');
    }

    public function testTheMessageOfMissingTerms(): void
    {
        self::assertSame('No terms for "de": build a Terms with the words of its language.', MissingTerms::forLocale('de')->getMessage());
    }

    public function testCitations(): void
    {
        $cite = new Cite('a', '12', Locator::Chapter, 'see', 'and after');

        self::assertSame('a', $cite->id);
        self::assertSame('12', $cite->locator);
        self::assertSame(Locator::Chapter, $cite->label);
        self::assertSame('see', $cite->prefix);
        self::assertSame('and after', $cite->suffix);
        self::assertSame(Locator::Page, new Cite('a')->label);
        self::assertEquals([new Cite('a'), new Cite('b')], Citation::of('a', 'b')->cites);
        self::assertEquals([$cite, new Cite('c')], new Citation($cite, new Cite('c'))->cites);
        self::assertEquals([new Cite('a')], Citation::of('a')->cites);
    }
}
