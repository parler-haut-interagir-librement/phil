<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit;

use Phil\DocumentImport\DocumentImporter;
use Phil\DocumentImport\Exception\InvalidDocument;
use Phil\DocumentImport\Exception\LimitExceeded;
use Phil\DocumentImport\Format;
use Phil\DocumentImport\Imported;
use Phil\DocumentImport\Internal\Builder;
use Phil\DocumentImport\Internal\Codepage;
use Phil\DocumentImport\Internal\Inline;
use Phil\DocumentImport\Internal\Role;
use Phil\DocumentImport\Internal\Rtf\RtfCapture;
use Phil\DocumentImport\Internal\Rtf\RtfContext;
use Phil\DocumentImport\Internal\Rtf\RtfField;
use Phil\DocumentImport\Internal\Rtf\RtfLexer;
use Phil\DocumentImport\Internal\Rtf\RtfReader;
use Phil\DocumentImport\Internal\Rtf\RtfState;
use Phil\DocumentImport\Internal\Rtf\RtfStyles;
use Phil\DocumentImport\Limits;
use Phil\DocumentImport\StyleMap;
use Phil\DocumentImport\StyleRole;
use Phil\DocumentImport\Tests\Unit\Fixture\Png;
use Phil\Ujo\Block;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocumentImporter::class)]
#[CoversClass(Builder::class)]
#[CoversClass(Codepage::class)]
#[CoversClass(Inline::class)]
#[CoversClass(Role::class)]
#[CoversClass(RtfReader::class)]
#[CoversClass(RtfLexer::class)]
#[CoversClass(RtfState::class)]
#[CoversClass(RtfCapture::class)]
#[CoversClass(RtfContext::class)]
#[CoversClass(RtfField::class)]
#[CoversClass(RtfStyles::class)]
final class RtfImportTest extends TestCase
{
    private const string HEAD = '{\rtf1\ansi\ansicpg1252\deff0{\fonttbl{\f0 Times;}}{\colortbl;\red255\green0\blue0;}'
        .'{\stylesheet{\s0 Normal;}{\s1\sbasedon0 heading 1;}{\s2\sbasedon1 Sub heading;}{\s3 Quote;}{\s4 Source Code;}{\s5\outlinelevel2 Outlined;}{\s6 Chapter Title;}{\s7 Author Note;}{\*\cs10 Default Paragraph Font;}}';

    public function testParagraphsHeadingsAndCharacterFormatting(): void
    {
        $imported = $this->unicode(self::HEAD
            .'{\info{\title My T\\\'eftle}{\author Ann}{\subject Rules}{\keywords dice, maps}{\doccomm A test}{\operator Bo}{\creatim\yr2026}}'
            .'\pard\s1 Heading one\par\pard\s2 Inherited\par\pard\s5 Outline style\par\pard\outlinelevel0 Direct outline\par'
            .'\pard Hello {\b bold} and {\i italic \ul under\ulnone} caf\\\'e9 U+8212? \uc0U+8211 done{\cf1 red}{\strike s\strike0}{\striked1 d}{\highlight3 m}{\super 2}{\sub i}{\super x\nosupersub y}{\b\i both\plain none}.\par'
            .'\pard\s3 A quote\par\pard\s4 code line\par\pard\s4 second\par'
            .'\pard\qc Centered\line next\tab tab\~nb\-soft\_hyph\emdash\endash\bullet\lquote\rquote\ldblquote\rdblquote\par'
            .'\pard\qr Right\par\pard\qj Justified\par\pard\ql Left\par'
            .'\pard {\v hidden} shown\par\pard\par}');

        self::assertSame([
            ['header', ['text' => 'Heading one', 'level' => 1]],
            ['header', ['text' => 'Inherited', 'level' => 1]],
            ['header', ['text' => 'Outline style', 'level' => 3]],
            ['header', ['text' => 'Direct outline', 'level' => 1]],
        ], \array_slice($this->blocks($imported), 0, 4));
        self::assertSame('Hello <b>bold</b> and <i>italic <u class="cdx-underline">under</u></i> café — –donered<s class="cdx-strikethrough">sd</s><mark class="cdx-marker">m</mark><sup>2</sup><sub>i</sub><sup>x</sup>y<b><i>both</i></b>none.', $this->blocks($imported)[4][1]['text']);
        self::assertSame(['quote', ['text' => 'A quote', 'caption' => '', 'alignment' => 'left']], $this->blocks($imported)[5]);
        self::assertSame(['code', ['code' => "code line\nsecond"]], $this->blocks($imported)[6]);
        self::assertSame("Centered<br>next tab\u{A0}nb\u{AD}soft\u{2011}hyph—–•‘’“”", $this->blocks($imported)[7][1]['text']);
        self::assertSame(['center', 'right', 'justify', null], array_map($this->alignment(...), \array_slice($imported->document->blocks, 7, 4)));
        self::assertSame(['paragraph', ['text' => 'shown']], $this->blocks($imported)[11]);
        self::assertCount(12, $imported->document->blocks);
        self::assertSame(['title' => 'My Tïtle', 'creator' => 'Ann', 'subject' => 'Rules', 'keywords' => 'dice, maps', 'description' => 'A test'], $imported->metadata);
        self::assertSame([['formatting', 'colour', 1], ['hidden-text', '', 1]], $this->report($imported));
    }

    public function testTheStyleMapComesFirst(): void
    {
        $imported = new DocumentImporter(styles: new StyleMap(['chapter title' => StyleRole::Heading2, 'Author Note' => StyleRole::Omit, 'Heading 1' => StyleRole::Quote]))
            ->import(self::HEAD.'\pard\s6 Mapped\par\pard\s7 Left out\par\pard\s2 Parent mapped\par\pard\s6\ls1 Not a list\par}', Format::Rtf);

        self::assertSame([
            ['header', ['text' => 'Mapped', 'level' => 2]],
            ['quote', ['text' => 'Parent mapped', 'caption' => '', 'alignment' => 'left']],
            ['header', ['text' => 'Not a list', 'level' => 2]],
        ], $this->blocks($imported));
        self::assertSame([['style', 'Author Note', 1]], $this->report($imported));
    }

    public function testListsTablesNotesAndFields(): void
    {
        $imported = $this->import(self::HEAD
            .'{\pntext 1.\tab}\pard\ls1 First item\par{\listtext 2.\tab}\pard\ls1 Second\par{\listtext \\\'95\tab}\pard\ls1\ilvl1 Nested\par'
            .'{\listtext \\\'95\tab}\pard\ls2 Bullet\par\pard\ls3 No marker\par'
            .'\pard Note here{\super\chftn}{\footnote\pard\plain{\super\chftn} The {\i note}.\par Second line.}{\footnote\ftnalt End}\par'
            .'\trowd\trhdr\cellx1000\cellx2000\pard\intbl A\cell B\par two\cell\row'
            .'\trowd\pard\intbl C\cell{\pict\pngblip '.bin2hex(Png::bytes()).'}D\cell\row'
            .'\pard {\field{\*\fldinst HYPERLINK "https://example.com/"}{\fldrslt link text}} after '
            .'{\field{\*\fldinst {HYPERLINK \\\\l "anchor"}}{\fldrslt inside}} {\field{\*\fldinst PAGE}{\fldrslt 3}}\par'
            .'{\field{\*\fldinst TOC \o "1-3"}{\fldrslt old entries}}'
            .'\pard Nested{\nestcell}cells{\nestrow}\par\page\pard After break\par}');

        $item = static fn (string $content, array $items = []): array => ['content' => $content, 'meta' => [], 'items' => $items];
        $png = ['file' => ['url' => 'media/image-1.png'], 'caption' => '', 'withBorder' => false, 'withBackground' => false, 'stretched' => false];

        self::assertSame([
            ['list', ['style' => 'ordered', 'meta' => [], 'items' => [$item('First item'), $item('Second', [$item('Nested')])]]],
            ['list', ['style' => 'unordered', 'meta' => [], 'items' => [$item('Bullet')]]],
            ['list', ['style' => 'unordered', 'meta' => [], 'items' => [$item('No marker')]]],
            ['paragraph', ['text' => 'Note here<sup data-footnote="fn-1"><a href="#footnote-fn-1">1</a></sup><sup data-footnote="en-1"><a href="#footnote-en-1">1</a></sup>']],
            ['table', ['withHeadings' => true, 'stretched' => false, 'content' => [['A', 'B<br>two'], ['C', 'D']]]],
            ['image', $png],
            ['paragraph', ['text' => '<a href="https://example.com/">link text</a> after inside 3']],
            ['toc', ['title' => '', 'depth' => 3, 'wide' => false]],
            ['paragraph', ['text' => 'Nested cells']],
            ['pageBreak', []],
            ['paragraph', ['text' => 'After break']],
            ['footnote', ['id' => 'fn-1', 'text' => 'The <i>note</i>.<br>Second line.']],
            ['footnote', ['id' => 'en-1', 'text' => 'End']],
        ], $this->blocks($imported));
        self::assertSame(Png::bytes(), $imported->media['media/image-1.png']->contents);
        self::assertSame([['internal-link', '', 1], ['nested-table', '', 1]], $this->report($imported));
    }

    public function testPicturesHeadersCommentsObjectsAndShapes(): void
    {
        $imported = $this->import(self::HEAD
            .'\pard Before{\*\shppict{\pict{\*\picprop{\sp{\sn wzDescription}{\sv A dot}}{\sp{\sn other}{\sv x}}}\pngblip\picw1 '.bin2hex(Png::bytes()).'}}{\nonshppict{\pict\wmetafile8 00}}after\par'
            .'\pard{\pict\jpegblip\bin4 '."\xFF\xD8\xFF\xE0".'}\par\pard{\pict\emfblip 0100}{\pict 00}\par'
            .'{\header \pard Running head\par}{\footer \pard \par}{\footerf Page}'
            .'\pard Text{\*\annotation A comment}{\object\objemb{\*\objdata 01}}{\shp{\*\shpinst x}}{\*\do drawing}{\*\unknown gone}{\*\shptxt box} end\par}');

        $image = static fn (string $url, string $alt = ''): array => ['file' => ['url' => $url], 'caption' => '', ...'' === $alt ? [] : ['alt' => $alt], 'withBorder' => false, 'withBackground' => false, 'stretched' => false];

        self::assertSame([
            ['paragraph', ['text' => 'Before']],
            ['image', $image('media/image-1.png', 'A dot')],
            ['paragraph', ['text' => 'after']],
            ['image', $image('media/image-2.jpg')],
            ['paragraph', ['text' => 'Text end']],
        ], $this->blocks($imported));
        self::assertSame([['image', 'emfblip', 1], ['image', 'unknown', 1], ['header-footer', 'header', 1], ['header-footer', 'footer', 1], ['comment', '', 1], ['embedded-object', '', 1], ['drawing', 'shape', 2], ['text-box', '', 1]], $this->report($imported));
    }

    public function testUnicodeSurrogatesAndCodePages(): void
    {
        self::assertSame('𝐀 x �é Ж', $this->blocks($this->unicode('{\rtf1\ansi U+-10187U+-9216 ? x U+-9216 ?\\\'e9 U+1046?\par}'))[0][1]['text']);
        self::assertSame('Ж', $this->blocks($this->import('{\rtf1\ansi\ansicpg1251 \\\'c6\par}'))[0][1]['text']);
        self::assertSame('é', $this->blocks($this->import('{\rtf1\mac \\\'8e\par}'))[0][1]['text']);
        self::assertSame('é', $this->blocks($this->import('{\rtf1\pc \\\'82\par}'))[0][1]['text']);
        self::assertSame('a{b}\\c', $this->blocks($this->import("{\\rtf1 a\\{b\\}\\\\c\r\n\\\r\n}"))[0][1]['text']);
        self::assertSame('x', $this->blocks($this->unicode('{\rtf1 xU+0 }'))[0][1]['text']);
    }

    public function testUnclosedGroupsAndStrayBracesAreTolerated(): void
    {
        self::assertSame([['paragraph', ['text' => '<b>Open</b>']]], $this->blocks($this->import('{\rtf1 {\b Open')));
        self::assertSame([['paragraph', ['text' => 'a b']]], $this->blocks($this->import('{\rtf1 a}} b\par')));
    }

    public function testAFileThatIsNotRtfIsRefused(): void
    {
        $this->expectExceptionObject(InvalidDocument::because('The file is not an RTF document: it does not start with "{\rtf".'));
        new DocumentImporter()->import('{\rtfx', Format::Rtf);
    }

    public function testGroupsNestNoDeeperThanTheLimit(): void
    {
        $this->expectExceptionObject(LimitExceeded::because('The RTF document nests groups deeper than 3.'));
        new DocumentImporter(limits: new Limits(depth: 3))->import('{\rtf1 {{{x}}}}', Format::Rtf);
    }

    public function testTextOutsideTheBodyIsBounded(): void
    {
        $this->expectExceptionObject(LimitExceeded::because('The RTF document holds more than 20 bytes outside its text.'));
        new DocumentImporter(limits: new Limits(entryBytes: 10))->import('{\rtf1 {\pict\pngblip '.str_repeat('0', 30).'}}', Format::Rtf);
    }

    public function testBinaryDataIsBoundedByTheLimit(): void
    {
        $tokens = iterator_to_array(RtfLexer::tokens('\bin99 abcdef\par', 3), false);

        self::assertSame([[RtfLexer::BINARY, 'abc', null], [RtfLexer::TEXT, 'def', null], [RtfLexer::WORD, 'par', null]], $tokens);
        self::assertSame([[RtfLexer::WORD, 'b', -2], [RtfLexer::SYMBOL, '*', null]], iterator_to_array(RtfLexer::tokens('\b-2 \*\\', 3), false));
    }

    public function testTheHeaderMayFollowABomAndBlanksButNothingElse(): void
    {
        self::assertSame([['paragraph', ['text' => 'Hi']]], $this->blocks($this->import("\n\t {\\rtf1 Hi}")));
        self::assertCount(1, $this->blocks($this->import("\u{FEFF}{\\rtf1 Hi}")));

        $this->expectExceptionObject(InvalidDocument::because('The file is not an RTF document: it does not start with "{\rtf".'));
        $this->import('x {\rtf1 Hi}');
    }

    public function testTheFallbackOfAUnicodeCharacterIsSkippedOnlyRightAfterIt(): void
    {
        self::assertSame('—<b>?x</b>', $this->blocks($this->unicode('{\rtf1 U+8212\b ?x}'))[0][1]['text']);
        self::assertSame('—?x', $this->blocks($this->unicode('{\rtf1 U+8212\bin1 u?x}'))[0][1]['text']);
        self::assertSame('—?x', $this->blocks($this->unicode('{\rtf1 U+8212{?x}}'))[0][1]['text']);
        self::assertSame('—x', $this->blocks($this->unicode('{\rtf1 \uc U+8212?x}'))[0][1]['text']);
        self::assertSame('—?x', $this->blocks($this->unicode('{\rtf1 \uc U+8212??x}'))[0][1]['text']);
        self::assertSame('—?x', $this->blocks($this->unicode('{\rtf1 \uc0 U+8212?x}'))[0][1]['text']);
        self::assertSame('AB', $this->blocks($this->unicode('{\rtf1 AU+0?B}'))[0][1]['text']);
        self::assertSame('AB', $this->blocks($this->unicode('{\rtf1 AU+ ?B}'))[0][1]['text']);
    }

    public function testSurrogatesAreJoinedFromTheFirstToTheLastOfTheirRanges(): void
    {
        self::assertSame("\u{10000}", $this->blocks($this->unicode('{\rtf1 U+55296?U+56320?}'))[0][1]['text']);
        self::assertSame("\u{10FFFF}", $this->blocks($this->unicode('{\rtf1 U+56319?U+57343?}'))[0][1]['text']);
        self::assertSame("\u{FFFD}", $this->blocks($this->unicode('{\rtf1 U+57343?}'))[0][1]['text']);
    }

    public function testGroupsLeftOpenAreClosedAtTheEnd(): void
    {
        self::assertSame([
            ['paragraph', ['text' => 'Text<sup data-footnote="fn-1"><a href="#footnote-fn-1">1</a></sup>']],
            ['footnote', ['id' => 'fn-1', 'text' => 'Note']],
        ], $this->blocks($this->import('{\rtf1 Text{\footnote Note')));
    }

    public function testATableStillOpenAtTheEndIsWritten(): void
    {
        $table = static fn (array $rows): array => ['table', ['withHeadings' => false, 'stretched' => false, 'content' => $rows]];

        self::assertSame([$table([['A']])], $this->blocks($this->import('{\rtf1 \trowd\intbl A\cell\row\pard}')));
        self::assertSame([$table([['A']])], $this->blocks($this->import('{\rtf1 \trowd\intbl A\cell\pard}')));
        self::assertSame([$table([['A']])], $this->blocks($this->import('{\rtf1 \intbl A\par}')));
        self::assertSame([$table([['A']]), ['pageBreak', []]], $this->blocks($this->import('{\rtf1 \trowd\intbl A\cell\row\pard\page}')));
        self::assertSame([['paragraph', ['text' => 'A']], ['pageBreak', []], ['paragraph', ['text' => 'B']]], $this->blocks($this->import('{\rtf1 A\page B}')));
        self::assertSame([$table([['A', 'B']])], $this->blocks($this->import('{\rtf1 \trowd\intbl A\cell B\par\pard}')));
        self::assertSame(['table', 'image'], array_column($this->blocks($this->import('{\rtf1 \trowd\intbl A\cell\row\pard{\pict\pngblip '.bin2hex(Png::bytes()).'}}')), 0));
    }

    public function testTablesDoNotShareTheirHeadings(): void
    {
        $blocks = $this->blocks($this->import('{\rtf1 \trowd\trhdr\intbl A\cell\row\pard B\par\trowd\intbl C\cell\row\pard D\par\trowd\intbl E\cell\row\pard G\par\trowd\trhdr\intbl F\cell\row\pard}'));

        self::assertSame([true, false, false, true], array_map(static fn (array $block): bool => true === $block[1]['withHeadings'], array_values(array_filter($blocks, static fn (array $block): bool => 'table' === $block[0]))));
    }

    public function testWhatFollowsTextInAGroupOpensNoDestination(): void
    {
        self::assertSame('Hello ZZ done', $this->blocks($this->import('{\rtf1 {Hello \footnote ZZ} done}'))[0][1]['text']);
        self::assertSame("\u{A0}X", $this->blocks($this->import('{\rtf1 {\~\footnote X}}'))[0][1]['text']);
        self::assertSame('<b>X</b>', $this->blocks($this->import('{\rtf1 {\b\footnote X}}'))[0][1]['text']);
    }

    public function testWhatAGroupOutsideTheTextHoldsIsBoundedAsAWhole(): void
    {
        $imported = new DocumentImporter(limits: new Limits(entryBytes: 10))->import('{\rtf1 {\info{\title 1234567890}{\author abcdefghij}}}', Format::Rtf);
        self::assertSame(['title' => '1234567890', 'creator' => 'abcdefghij'], $imported->metadata);

        $this->expectExceptionObject(LimitExceeded::because('The RTF document holds more than 20 bytes outside its text.'));
        new DocumentImporter(limits: new Limits(entryBytes: 10))->import('{\rtf1 {\info{\title 12345678901}{\author abcdefghijk}}}', Format::Rtf);
    }

    public function testControlsOutsideAnyDestinationAreHarmless(): void
    {
        self::assertSame("\u{FFFD}", $this->blocks($this->import('{\rtf1 {\info{\u55296}}\u56320?}'))[0][1]['text'], 'a group left out leaves no half of a pair');
        self::assertSame('AB', $this->blocks($this->import('{\rtf1 A\bin3 xyzB}'))[0][1]['text']);
        self::assertSame('ab', $this->blocks($this->import('{\rtf1 a\|b}'))[0][1]['text']);
        self::assertSame('x', $this->blocks($this->import('{\rtf1 {\fldrslt x}}'))[0][1]['text']);
        self::assertSame('x', $this->blocks($this->import('{\rtf1 {\fldinst HYPERLINK "https://a.test/"}x}'))[0][1]['text']);
        self::assertSame('AB', $this->blocks($this->import('{\rtf1 A{\info{\page}}B}'))[0][1]['text']);
    }

    public function testAnIgnorableStyleOfTheSheetIsNotAStyle(): void
    {
        $map = new StyleMap(['chapter title' => StyleRole::Heading2]);
        $read = static fn (string $rtf): array => array_map(static fn (Block $block): array => [$block->type, $block->data['text'] ?? null, $block->data['level'] ?? null], new DocumentImporter(styles: $map)->import($rtf, Format::Rtf)->document->blocks);

        self::assertSame([['header', 'X', 2]], $read('{\rtf1 {\stylesheet{\s5 Chapter Title;{\*\ignored zzz}}}\pard\s5 X\par}'));
        self::assertSame([['paragraph', 'X', null]], $read('{\rtf1 {\stylesheet{\*\s5 Chapter Title;}}\pard\s5 X\par}'));
        self::assertSame([['paragraph', 'Text', null]], $read('{\rtf1 Text{\*\cs10 gone}\par}'));
        self::assertSame([['header', 'X', 2]], $read('{\rtf1 {\stylesheet{\s Chapter Title;}}\pard X\par}'));
        self::assertSame([['header', 'X', 2]], $read('{\rtf1 {\stylesheet{\s0 Title;}{\s5\sbasedon Chapter Title;}}\pard\s5 X\par}'), 'a style based on none is based on the style 0');
    }

    public function testAnIgnorableDestinationOfAKnownKindIsRead(): void
    {
        $ordered = static fn (bool $on): array => [['list', ['style' => $on ? 'ordered' : 'unordered', 'meta' => [], 'items' => [['content' => 'Item', 'meta' => [], 'items' => []]]]]];

        self::assertSame($ordered(true), $this->blocks($this->import('{\rtf1 {\*\listtext 1.\tab}\pard\ls1 Item\par}')));
        self::assertSame($ordered(true), $this->blocks($this->import('{\rtf1 {\listtext  1.}\pard\ls1 Item\par}')), 'the marker is trimmed');
        self::assertSame($ordered(false), $this->blocks($this->import('{\rtf1 {\listtext -1.}\pard\ls1 Item\par}')), 'a marker starts with its number');
        self::assertSame([['image', ['file' => ['url' => 'media/image-1.png'], 'caption' => '', 'withBorder' => false, 'withBackground' => false, 'stretched' => false]]], $this->blocks($this->import('{\rtf1 {\*\shppict{\pict\pngblip '.bin2hex(Png::bytes()).'}}}')));
    }

    public function testFieldsKeepTheirLinksAndTablesOfContents(): void
    {
        $toc = static fn (string $first): array => [['paragraph', ['text' => $first]], ['toc', ['title' => '', 'depth' => 3, 'wide' => false]], ['paragraph', ['text' => 'After']]];
        $toc = $toc('Before');

        self::assertSame($toc, $this->blocks($this->import('{\rtf1 Before{\field{\*\fldinst TOC \o "1-3"}{\fldrslt old}}\pard After\par}')));
        self::assertSame([
            ['table', ['withHeadings' => false, 'stretched' => false, 'content' => [['A']]]],
            ['toc', ['title' => '', 'depth' => 3, 'wide' => false]],
        ], $this->blocks($this->import('{\rtf1 \trowd\intbl A\cell\row\pard{\field{\*\fldinst TOC}{\fldrslt old}}}')));
        self::assertSame('5', $this->blocks($this->import('{\rtf1 {\field{\*\fldinst PAGEREF TOC}{\fldrslt 5}}}'))[0][1]['text']);
        self::assertSame('<a href="https://b.test/">B</a>', $this->blocks($this->import('{\rtf1 {\field{\*\fldinst HYPERLINK "https://a.test/"}{\fldrslt {\field{\*\fldinst HYPERLINK "https://b.test/"}{\fldrslt B}}}}}'))[0][1]['text']);
        self::assertSame('<a href="https://a.test/">A</a>', $this->blocks($this->import('{\rtf1 {\field{\*\fldinst HYPERLINK " https://a.test/ "}{\fldrslt A}}}'))[0][1]['text']);

        $imported = $this->import('{\rtf1 {\field{\*\fldinst X HYPERLINK \\\\l a}{\fldrslt t}}}');
        self::assertSame('t', $this->blocks($imported)[0][1]['text']);
        self::assertTrue($imported->report->isEmpty());
    }

    public function testCharacterFormattingCanBeTurnedOff(): void
    {
        self::assertSame('<b><i>x</i></b><i>y</i>z', $this->blocks($this->import('{\rtf1 \b\i x\b0 y\i0 z}'))[0][1]['text']);
        self::assertSame('<u class="cdx-underline">a</u>b', $this->blocks($this->import('{\rtf1 \ul a\ulnone b}'))[0][1]['text']);
        self::assertSame('<sub>i</sub>x', $this->blocks($this->import('{\rtf1 \sub i\nosupersub x}'))[0][1]['text']);
        self::assertSame('<mark class="cdx-marker">a</mark>b', $this->blocks($this->import('{\rtf1 \highlight3 a\highlight0 b}'))[0][1]['text']);

        $imported = $this->import('{\rtf1 \cf0 a}');
        self::assertTrue($imported->report->isEmpty());
    }

    public function testTheCodePageComesFromTheDocumentOrIsTheDefault(): void
    {
        self::assertSame('é', $this->blocks($this->import('{\rtf1\ansicpg\\\'e9\par}'))[0][1]['text']);
        self::assertSame('é', $this->blocks($this->import('{\rtf1\pca \\\'82\par}'))[0][1]['text']);
        self::assertSame('é', $this->blocks($this->import('{\rtf1\pc \\\'82\par}'))[0][1]['text']);
    }

    public function testParagraphsEndAtSectionsAndRawLineEnds(): void
    {
        self::assertSame([['paragraph', ['text' => 'A']], ['paragraph', ['text' => 'B']]], $this->blocks($this->import('{\rtf1 A\sect B}')));
        self::assertSame([['paragraph', ['text' => 'A']], ['paragraph', ['text' => 'B']]], $this->blocks($this->import("{\\rtf1 A\\\rB}")));
        self::assertSame([['paragraph', ['text' => 'A']], ['paragraph', ['text' => 'BC']]], $this->blocks($this->import("{\\rtf1 A\\\nBC}")));
        self::assertSame('AB', $this->blocks($this->import("{\\rtf1 A\nB}"))[0][1]['text']);
        self::assertSame('Xabc', $this->blocks($this->import('{\rtf1 X\bin-1 abc}'))[0][1]['text']);
        self::assertSame('x', $this->blocks($this->import('{\rtf1 \bin0 x}'))[0][1]['text']);
        self::assertSame('x', $this->blocks($this->import('{\rtf1 \bin x}'))[0][1]['text']);
    }

    public function testListLevelsAreKeptBetweenZeroAndEight(): void
    {
        $items = '';

        foreach ([0, 1, 2, 3, 4, 5, 6, 7, 8, 9] as $level) {
            $items .= '\pard\ls1\ilvl'.$level.' L'.$level.'\par';
        }

        $blocks = $this->blocks($this->import('{\rtf1 '.$items.'}'));
        self::assertSame([['L0'], ['L1'], ['L2'], ['L3'], ['L4'], ['L5'], ['L6'], ['L7'], ['L8', 'L9']], $this->levels($blocks[0][1]['items']));

        $flat = $this->blocks($this->import('{\rtf1 \pard\ls1\ilvl0 A\par\pard\ls1\ilvl0 B\par\pard\ls1\ilvl-1 C\par\pard\ls1\ilvl D\par}'));
        self::assertSame([['A', 'B', 'C', 'D']], $this->levels($flat[0][1]['items']));
    }

    public function testAMarkerWithoutAListJoinsTheListZero(): void
    {
        $blocks = $this->blocks($this->import('{\rtf1 {\listtext \\\'95\tab}A\par\pard\ls0 B\par}'));

        self::assertSame([['list', ['style' => 'unordered', 'meta' => [], 'items' => [['content' => 'A', 'meta' => [], 'items' => []], ['content' => 'B', 'meta' => [], 'items' => []]]]]], $blocks);
    }

    public function testASectionBreakIsNotAStyleAndTheStyleNumberFallsBackToZero(): void
    {
        $map = new StyleMap(['mapped' => StyleRole::Quote]);
        $imported = new DocumentImporter(styles: $map)->import('{\rtf1 {\stylesheet{\s0 Mapped;}{\s1 Other;}}\pard\s1 A\par\pard B\par\pard\s1\s C\par}', Format::Rtf);

        self::assertSame(['paragraph', 'quote', 'quote'], array_map(static fn (Block $block): string => $block->type, $imported->document->blocks));
    }

    public function testBlankDestinationsAndInformationAreNotKept(): void
    {
        $imported = $this->import('{\rtf1 {\info{\title   }{\author  Ann  }}{\header   }{\footer   }Text\par}');

        self::assertSame(['creator' => 'Ann'], $imported->metadata);
        self::assertSame([], $this->report($imported));
    }

    public function testThePictureDescriptionAndHexAreTrimmed(): void
    {
        $hex = bin2hex(Png::bytes());
        $picture = static fn (string $hex, string $description): string => '{\rtf1 {\pict{\*\picprop{\sp{\sn wzDescription }{\sv  '.$description.'  }}}\pngblip '.$hex.'}\par}';

        foreach ([$hex, $hex.'a', $hex.'abc'] as $variant) {
            self::assertSame(
                [['image', ['file' => ['url' => 'media/image-1.png'], 'caption' => '', 'alt' => 'A dot', 'withBorder' => false, 'withBackground' => false, 'stretched' => false]]],
                $this->blocks($this->import($picture($variant, 'A dot'))),
            );
        }

        self::assertSame([['paragraph', ['text' => 'x']]], $this->blocks($this->import('{\rtf1 {\picprop{\sp{\sn wzDescription}{\sv alt}}}x}')));
        self::assertSame([['paragraph', ['text' => 'x']]], $this->blocks($this->import('{\rtf1 {\sv alt}x}')));
    }

    /**
     * An RTF document whose `U+` stand for the backslash and `u` of its Unicode control words.
     */
    private function unicode(string $rtf): Imported
    {
        return $this->import(str_replace('U+', '\\u', $rtf));
    }

    private function import(string $rtf): Imported
    {
        return new DocumentImporter()->import($rtf, filename: 'letter.rtf');
    }

    /**
     * The contents of the first item of each level of a nested list, from the outer level in.
     *
     * @return list<list<string>>
     */
    private function levels(mixed $items): array
    {
        $levels = [];

        while (\is_array($items) && [] !== $items) {
            $levels[] = array_values(array_map(static fn (mixed $item): string => \is_array($item) && \is_string($item['content'] ?? null) ? $item['content'] : '', $items));
            $first = $items[0] ?? null;
            $items = \is_array($first) ? ($first['items'] ?? null) : null;
        }

        return $levels;
    }

    /**
     * @return list<array{string, array<array-key, mixed>}>
     */
    private function blocks(Imported $imported): array
    {
        return array_map(static function (Block $block): array {
            $array = $block->toArray();

            return [$array['type'], $array['data']];
        }, $imported->document->blocks);
    }

    private function alignment(Block $block): ?string
    {
        $tune = $block->tune('alignment');

        return \is_array($tune) && \is_string($tune['alignment'] ?? null) ? $tune['alignment'] : null;
    }

    /**
     * @return list<array{string, string, int}>
     */
    private function report(Imported $imported): array
    {
        return array_map(static fn (array $row): array => [$row['what'], $row['detail'], $row['count']], $imported->report->toArray());
    }
}
