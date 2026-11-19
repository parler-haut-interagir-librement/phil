<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit;

use Phil\DocumentImport\Internal\Inline;
use Phil\DocumentImport\Internal\Mark;
use Phil\DocumentImport\Internal\Pdf\PdfRun;
use Phil\DocumentImport\Internal\Role;
use Phil\DocumentImport\Internal\Rtf\RtfCapture;
use Phil\DocumentImport\Internal\Rtf\RtfContext;
use Phil\DocumentImport\Internal\Rtf\RtfField;
use Phil\DocumentImport\Internal\Rtf\RtfLexer;
use Phil\DocumentImport\Internal\Rtf\RtfState;
use Phil\DocumentImport\Internal\Rtf\RtfStyles;
use Phil\DocumentImport\StyleMap;
use Phil\DocumentImport\StyleRole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RtfCapture::class)]
#[CoversClass(RtfContext::class)]
#[CoversClass(RtfField::class)]
#[CoversClass(RtfLexer::class)]
#[CoversClass(RtfState::class)]
#[CoversClass(RtfStyles::class)]
#[CoversClass(Inline::class)]
#[CoversClass(PdfRun::class)]
final class RtfPartsTest extends TestCase
{
    public function testTheStyleSheetNamesStylesByNumberAndFallsBackToTheNumber(): void
    {
        $styles = new RtfStyles(new StyleMap());
        $styles->add(3, 'Quote', null, null);

        self::assertSame(['Quote', 's9'], [$styles->name(3), $styles->name(9)]);
    }

    public function testTheNamesOfHeadingsAreReadWholeAndExactly(): void
    {
        $styles = new RtfStyles(new StyleMap());
        $roles = [];

        foreach (['Title', 'Heading 3', 'subheading 2', 'heading 2x', "heading 2\n", 'heading 10', 'HEADING 6'] as $number => $name) {
            $styles->add($number, $name, null, null);
            $roles[] = $styles->role($number, null);
        }

        self::assertSame([Role::Heading1, Role::Heading3, Role::Paragraph, Role::Paragraph, Role::Paragraph, Role::Paragraph, Role::Heading6], $roles);
    }

    public function testAStyleTakesItsRoleFromTheStyleItIsBasedOn(): void
    {
        $styles = new RtfStyles(new StyleMap());
        $styles->add(0, 'Heading 2', null, null);
        $styles->add(5, 'Plain', null, null);
        $styles->add(6, 'Based', 0, null);
        $styles->add(-2, 'Heading 4', null, null);
        $styles->add(7, 'Loop', 7, null);

        self::assertSame([Role::Paragraph, Role::Heading2, Role::Paragraph], [$styles->role(5, null), $styles->role(6, null), $styles->role(7, null)]);
    }

    public function testTheSheetKeepsParagraphStylesOnly(): void
    {
        $styles = new RtfStyles(new StyleMap());

        foreach (['cs', 'ds', 'ts'] as $kind) {
            $capture = new RtfCapture('style');
            $capture->properties = [$kind => 1, 's' => 5];
            $capture->text = 'Character;';
            $styles->define($capture);
        }

        self::assertSame('s5', $styles->name(5));

        $capture = new RtfCapture('style');
        $capture->text = ' Name ; ';

        $styles->define($capture);
        self::assertSame('Name', $styles->name(0), 'a style without a number is the style 0');

        $capture = new RtfCapture('style');
        $capture->properties = ['s' => 4, 'sbasedon' => 0, 'outlinelevel' => 2];
        $capture->text = 'Outlined;';

        $styles->define($capture);
        self::assertSame(Role::Heading3, $styles->role(4, null));
    }

    public function testTheMapOfTheApplicationComesBeforeTheNames(): void
    {
        $styles = new RtfStyles(new StyleMap(['Title' => StyleRole::Quote]));
        $styles->add(1, 'Title', null, null);

        self::assertTrue($styles->isMapped(1));
        self::assertSame(Role::Quote, $styles->role(1, null));
        self::assertFalse($styles->isMapped(2));
    }

    public function testTheCharacterFormattingOfAGroupIsAList(): void
    {
        $state = new RtfState();
        $state->set(Mark::Bold, true);
        $state->set(Mark::Italic, true);
        $state->set(Mark::Bold, false);

        self::assertSame([Mark::Italic], $state->marks());
    }

    public function testAFieldKnowsItsTableOfContentsAndItsLink(): void
    {
        $field = new RtfField();

        self::assertFalse($field->read('PAGEREF TOC \h'));
        self::assertFalse($field->toc);
        self::assertFalse($field->read(' TOC \o "1-3"'));
        self::assertTrue($field->toc);
        self::assertFalse($field->read('X HYPERLINK \l a'));
        self::assertNull($field->link);
        self::assertTrue($field->read(' HYPERLINK \l anchor'));
        self::assertFalse($field->read('HYPERLINK " https://a.test/ "'));
        self::assertSame('https://a.test/', $field->link);
    }

    public function testAPictureDescriptionIsGivenToItsPictureOnly(): void
    {
        $picture = new RtfCapture('pict');
        $properties = new RtfCapture('picprop', $picture);
        $properties->properties['sn'] = 'wzDescription';
        $value = new RtfCapture('sv', $properties);
        $value->text = "  Alt \n";
        $value->describePicture();

        self::assertSame('Alt', $picture->properties['description']);

        $loose = new RtfCapture('picprop');
        $loose->properties['sn'] = 'wzDescription';
        new RtfCapture('sv', $loose)->describePicture();
        new RtfCapture('sv')->describePicture();
        $other = new RtfCapture('picprop', new RtfCapture('header'));
        $other->properties['sn'] = 'wzDescription';
        new RtfCapture('sv', $other)->describePicture();

        self::assertSame(['sn' => 'wzDescription'], $loose->properties);
        self::assertSame([], $other->parent?->properties);
    }

    public function testATableRowIsMadeOfItsCellsAndTheFirstRowMayBeTheHeading(): void
    {
        $context = new RtfContext(true);
        $context->cell = ['A', ''];
        $context->inline->text('B', []);
        $context->endCell();
        self::assertSame(['A<br>B'], $context->row);

        $row = static function (RtfContext $context, bool $header): void {
            $context->headerRow = $header;
            $context->inline->text('x', []);
            $context->endRow();
        };

        $first = new RtfContext(true);
        $row($first, true);
        self::assertTrue($first->headings);
        self::assertFalse($first->headerRow);
        $row($first, false);
        self::assertCount(2, $first->rows);

        $second = new RtfContext(true);
        $row($second, false);
        $row($second, true);
        self::assertFalse($second->headings, 'only the first row heads the table');

        $none = new RtfContext(true);
        $row($none, false);
        self::assertFalse($none->headings);
        self::assertFalse($none->headerRow);

        $cells = new RtfContext(true);
        $cells->cell = ['A'];
        $cells->endRow();
        self::assertSame([['A']], $cells->rows);

        $empty = new RtfContext(true);
        $empty->endRow();
        self::assertSame([], $empty->rows);
    }

    public function testAParagraphStartsOverFromTheStyleZero(): void
    {
        $context = new RtfContext(true);
        $context->style = 5;
        $context->resetParagraph();

        self::assertSame(0, $context->style);
    }

    public function testTheLexerContinuesAfterLineEndsAndReadsBinaryDataByItsLength(): void
    {
        $tokens = static fn (string $rtf): array => iterator_to_array(RtfLexer::tokens($rtf, 100), false);
        $text = static fn (string $text): array => [RtfLexer::TEXT, $text, null];
        $par = [RtfLexer::WORD, 'par', null];
        $binary = static fn (string $bytes): array => [RtfLexer::BINARY, $bytes, null];

        self::assertSame([$text('A'), $text('B')], $tokens("A\nB"));
        self::assertSame([$text('X'), $binary(''), $text('abc')], $tokens('X\bin-1 abc'));
        self::assertSame([$binary(''), $text('x')], $tokens('\bin0 x'));
        self::assertSame([$binary(''), $text('x')], $tokens('\bin x'));
        self::assertSame([$text('A'), $par, $text('B')], $tokens("A\\\rB"));
        self::assertSame([$text('A'), $par, $text('BC')], $tokens("A\\\nBC"));
    }

    public function testTheLastCharacterOfATextIsTakenBackWhole(): void
    {
        $inline = new Inline();
        $inline->text('café', []);
        $inline->dropLast(1);

        self::assertSame('caf', $inline->toHtml());
    }

    public function testARunIsNeitherBoldNorItalicUnlessItSaysSo(): void
    {
        $run = new PdfRun('a', 0.0, 1.0, 0.0, 10.0);

        self::assertFalse($run->bold);
        self::assertFalse($run->italic);
    }
}
