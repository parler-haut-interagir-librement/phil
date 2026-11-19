<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit;

use Phil\DocumentImport\DocumentImporter;
use Phil\DocumentImport\Exception\InvalidDocument;
use Phil\DocumentImport\Exception\LimitExceeded;
use Phil\DocumentImport\Format;
use Phil\DocumentImport\Imported;
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
