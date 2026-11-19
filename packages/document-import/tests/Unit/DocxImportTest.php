<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit;

use Phil\DocumentImport\DocumentImporter;
use Phil\DocumentImport\Exception\InvalidDocument;
use Phil\DocumentImport\Format;
use Phil\DocumentImport\Imported;
use Phil\DocumentImport\Internal\Builder;
use Phil\DocumentImport\Internal\Docx\DocxReader;
use Phil\DocumentImport\Internal\Docx\DocxStyles;
use Phil\DocumentImport\Internal\Inline;
use Phil\DocumentImport\Internal\Role;
use Phil\DocumentImport\Omitted;
use Phil\DocumentImport\StyleMap;
use Phil\DocumentImport\StyleRole;
use Phil\DocumentImport\Tests\Unit\Fixture\Docx;
use Phil\DocumentImport\Tests\Unit\Fixture\Png;
use Phil\DocumentImport\Tests\Unit\Fixture\Zip;
use Phil\Ujo\Block;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocumentImporter::class)]
#[CoversClass(Builder::class)]
#[CoversClass(DocxReader::class)]
#[CoversClass(DocxStyles::class)]
#[CoversClass(Inline::class)]
#[CoversClass(Role::class)]
final class DocxImportTest extends TestCase
{
    public function testParagraphsKeepTheCharacterFormattingUjoHas(): void
    {
        $imported = $this->import(Docx::body(self::p(
            self::r('plain ').self::r('bold', '<w:b/>').self::r(' and ', '<w:b/>').self::r('italic', '<w:b/><w:i/>')
            .self::r(' u', '<w:u w:val="single"/>').self::r(' s', '<w:strike/>').self::r(' d', '<w:dstrike/>')
            .self::r(' m', '<w:highlight w:val="yellow"/>').self::r('2', '<w:vertAlign w:val="superscript"/>')
            .self::r('i', '<w:vertAlign w:val="subscript"/>').self::r(' base', '<w:vertAlign w:val="baseline"/>')
            .self::r(' off', '<w:b w:val="0"/><w:i w:val="false"/><w:strike w:val="off"/><w:u w:val="none"/><w:highlight w:val="none"/>')
            .self::r(' <&> "q"'),
        )));

        self::assertSame([['paragraph', ['text' => 'plain <b>bold and <i>italic</i></b><u class="cdx-underline"> u</u><s class="cdx-strikethrough"> s d</s><mark class="cdx-marker"> m</mark><sup>2</sup><sub>i</sub> base off &lt;&amp;&gt; &quot;q&quot;']]], $this->blocks($imported));
        self::assertTrue($imported->report->isEmpty());
        self::assertSame(Format::Docx, $imported->format);
    }

    public function testRunContentsBecomeTextBreaksAndPageBreaks(): void
    {
        $imported = $this->import(Docx::body(self::p(
            '<w:r><w:t>a</w:t><w:tab/><w:t>b</w:t><w:br/><w:t>c</w:t><w:cr/><w:t>d</w:t><w:noBreakHyphen/><w:softHyphen/><w:sym w:font="Wingdings" w:char="F04A"/></w:r>'
            .'<w:r><w:t>before</w:t><w:br w:type="page"/><w:t>after</w:t><w:lastRenderedPageBreak/></w:r>',
        ).self::p('').self::p('<w:r><w:t xml:space="preserve">   </w:t></w:r>')));

        self::assertSame([
            ['paragraph', ['text' => "a b<br>c<br>d\u{2011}\u{AD}before"]],
            ['pageBreak', []],
            ['paragraph', ['text' => 'after']],
        ], $this->blocks($imported));
        self::assertSame(1, $imported->report->count(Omitted::Formatting));
        self::assertSame('symbol', $imported->report->omissions()[0]->detail);
    }

    public function testParagraphStylesMakeHeadingsQuotationsAndCode(): void
    {
        $imported = $this->import(Docx::body(
            self::p(self::r('Title'), '<w:pStyle w:val="Title"/>')
            .self::p(self::r('Two'), '<w:pStyle w:val="Heading2"/>')
            .self::p(self::r('Nine'), '<w:pStyle w:val="Heading9"/>')
            .self::p(self::r('Outline'), '<w:pStyle w:val="Chapter"/>')
            .self::p(self::r('Body outline'), '<w:pStyle w:val="BodyOutline"/>')
            .self::p(self::r('Said'), '<w:pStyle w:val="Quote"/>')
            .self::p(self::r('Intense'), '<w:pStyle w:val="IntenseQuote"/>')
            .self::p(self::r('Block'), '<w:pStyle w:val="BlockText"/>')
            .self::p(self::r('if (a < b) {'), '<w:pStyle w:val="SourceCode"/>')
            .self::p('<w:r><w:t>  x &amp;&amp; y;</w:t><w:br/><w:t>z</w:t></w:r>', '<w:pStyle w:val="HTMLPreformatted"/>')
            .self::p(self::r('}'), '<w:pStyle w:val="Code"/>')
            .self::p(self::r('Plain'), '<w:pStyle w:val="Unknown"/>')
            .self::p(self::r('Looping'), '<w:pStyle w:val="Loop"/>'),
        )->styles(
            $this->style('Title', 'Title').$this->style('Heading2', 'heading 2').$this->style('Heading9', 'heading 9')
            .$this->style('Outline', 'Outline', '<w:pPr><w:outlineLvl w:val="2"/></w:pPr>')
            .$this->style('Chapter', 'Chapter', '<w:basedOn w:val="Outline"/>')
            .$this->style('BodyOutline', 'Body', '<w:pPr><w:outlineLvl w:val="9"/></w:pPr>')
            .$this->style('Quote', 'Quote').$this->style('IntenseQuote', 'Intense Quote').$this->style('BlockText', 'Block Text')
            .$this->style('SourceCode', 'Source Code').$this->style('HTMLPreformatted', 'HTML Preformatted').$this->style('Code', 'code')
            .$this->style('Loop', 'Loop', '<w:basedOn w:val="Loop"/>'),
        ));

        self::assertSame([
            ['header', ['text' => 'Title', 'level' => 1]],
            ['header', ['text' => 'Two', 'level' => 2]],
            ['header', ['text' => 'Nine', 'level' => 6]],
            ['header', ['text' => 'Outline', 'level' => 3]],
            ['paragraph', ['text' => 'Body outline']],
            ['quote', ['text' => 'Said', 'caption' => '', 'alignment' => 'left']],
            ['quote', ['text' => 'Intense', 'caption' => '', 'alignment' => 'left']],
            ['quote', ['text' => 'Block', 'caption' => '', 'alignment' => 'left']],
            ['code', ['code' => "if (a < b) {\nx && y;\nz\n}"]],
            ['paragraph', ['text' => 'Plain']],
            ['paragraph', ['text' => 'Looping']],
        ], $this->blocks($imported));
    }

    public function testParagraphAlignmentBecomesTheAlignmentTune(): void
    {
        $body = '';

        foreach (['left', 'start', 'center', 'right', 'end', 'both', 'distribute'] as $alignment) {
            $body .= self::p(self::r($alignment), '<w:jc w:val="'.$alignment.'"/>');
        }

        $tunes = array_map($this->alignment(...), $this->import(Docx::body($body.self::p(self::r('h'), '<w:pStyle w:val="H"/><w:jc w:val="center"/>'))->styles($this->style('H', 'heading 1')))->document->blocks);

        self::assertSame([null, null, 'center', 'right', 'right', 'justify', 'justify', 'center'], $tunes);
    }

    public function testNumberedParagraphsBecomeNestedLists(): void
    {
        $imported = $this->import(Docx::body(
            $this->item('One', 1, 0).$this->item('One.a', 1, 1).$this->item('One.a.i', 1, 2).$this->item('Deep', 1, 4)
            .$this->item('Two', 1, 0).$this->item('Nested in another list', 2, 1).$this->item('Three', 1, 0)
            .$this->item('Bullet', 2, 0).$this->item('Roman', 3, 0).$this->item('Letter', 4, 0)
            .self::p(self::r('Ended'), '<w:numPr><w:ilvl w:val="0"/><w:numId w:val="0"/></w:numPr>')
            .self::p(self::r('By style'), '<w:pStyle w:val="ListBullet"/>')
            .$this->item('Undefined', 9, 0)
            .self::p(self::r('none'), '<w:numPr><w:numId w:val="5"/></w:numPr>'),
        )->numbering(
            $this->abstract('10', '<w:lvl w:ilvl="1"><w:numFmt w:val="bullet"/></w:lvl><w:lvl w:ilvl="0"><w:start w:val="3"/><w:numFmt w:val="decimal"/></w:lvl>')
            .$this->abstract('20', '<w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/></w:lvl>')
            .$this->abstract('30', '<w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="upperRoman"/></w:lvl>')
            .$this->abstract('40', '<w:lvl w:ilvl="0"><w:numFmt w:val="lowerLetter"/></w:lvl>')
            .$this->abstract('50', '<w:lvl w:ilvl="0"><w:numFmt w:val="none"/></w:lvl>')
            .$this->abstract('60', '<w:lvl w:ilvl="1"/>')
            .'<w:num w:numId="1"><w:abstractNumId w:val="10"/></w:num><w:num w:numId="2"><w:abstractNumId w:val="20"/></w:num>'
            .'<w:num w:numId="3"><w:abstractNumId w:val="30"/></w:num><w:num w:numId="4"><w:abstractNumId w:val="40"/></w:num>'
            .'<w:num w:numId="5"><w:abstractNumId w:val="50"/></w:num><w:num w:numId="6"><w:abstractNumId w:val="60"/></w:num>'
            .'<w:num w:numId="9"><w:abstractNumId w:val="99"/></w:num>',
        )->styles($this->style('ListBullet', 'List Bullet', '<w:basedOn w:val="ListBase"/>').$this->style('ListBase', 'List', '<w:pPr><w:numPr><w:numId w:val="2"/></w:numPr></w:pPr>')));

        $item = static fn (string $content, array $items = []): array => ['content' => $content, 'meta' => [], 'items' => $items];

        self::assertSame([
            ['list', ['style' => 'ordered', 'meta' => ['start' => 3], 'items' => [
                $item('One', [$item('One.a', [$item('One.a.i', [$item('Deep')])])]),
                $item('Two', [$item('Nested in another list')]),
                $item('Three'),
            ]]],
            ['list', ['style' => 'unordered', 'meta' => [], 'items' => [$item('Bullet')]]],
            ['list', ['style' => 'ordered', 'meta' => ['counterType' => 'upper-roman'], 'items' => [$item('Roman')]]],
            ['list', ['style' => 'ordered', 'meta' => ['counterType' => 'lower-alpha'], 'items' => [$item('Letter')]]],
            ['paragraph', ['text' => 'Ended']],
            ['list', ['style' => 'unordered', 'meta' => [], 'items' => [$item('By style')]]],
            ['paragraph', ['text' => 'Undefined']],
            ['list', ['style' => 'unordered', 'meta' => [], 'items' => [$item('none')]]],
        ], $this->blocks($imported));
    }

    public function testAListWithoutOuterLevelIsAParagraph(): void
    {
        $imported = $this->import(Docx::body($this->item('Only deeper', 6, 1))->numbering(
            $this->abstract('60', '<w:lvl w:ilvl="1"><w:numFmt w:val="bullet"/></w:lvl>').'<w:num w:numId="6"><w:abstractNumId w:val="60"/></w:num>',
        ));

        self::assertSame([['paragraph', ['text' => 'Only deeper']]], $this->blocks($imported));
    }

    public function testHyperlinksAndFieldsLinkTheirText(): void
    {
        $imported = $this->import(Docx::body(self::p(
            '<w:hyperlink r:id="rIdLink"><w:r><w:t>site</w:t></w:r></w:hyperlink>'
            .'<w:hyperlink w:anchor="_Toc1"><w:r><w:t> inside</w:t></w:r></w:hyperlink>'
            .'<w:hyperlink r:id="rIdMissing"><w:r><w:t> missing</w:t></w:r></w:hyperlink>'
            .'<w:hyperlink r:id="rIdInternal"><w:r><w:t> part</w:t></w:r></w:hyperlink>'
            .'<w:fldSimple w:instr=" HYPERLINK &quot;https://simple.test/&quot; "><w:r><w:t> simple</w:t></w:r></w:fldSimple>'
            .'<w:fldSimple w:instr=" PAGE "><w:r><w:t> 4</w:t></w:r></w:fldSimple>'
            .'<w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText xml:space="preserve"> HYPERLINK </w:instrText></w:r>'
            .'<w:r><w:instrText>"https://complex.test/"</w:instrText></w:r><w:r><w:fldChar w:fldCharType="separate"/></w:r>'
            .'<w:r><w:t> complex</w:t></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r>'
            .'<w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText>DATE</w:instrText></w:r><w:r><w:fldChar w:fldCharType="separate"/></w:r>'
            .'<w:r><w:t> today</w:t></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r>'
            .'<w:r><w:fldChar w:fldCharType="end"/><w:fldChar w:fldCharType="separate"/><w:instrText>stray</w:instrText><w:t> after</w:t></w:r>',
        ))->relationship('rIdLink', 'hyperlink', 'https://example.com/a?b=1&c=2', true)->relationship('rIdInternal', 'hyperlink', 'other.xml'));

        self::assertSame([['paragraph', ['text' => '<a href="https://example.com/a?b=1&amp;c=2">site</a> inside missing part<a href="https://simple.test/"> simple</a> 4<a href="https://complex.test/"> complex</a> today after']]], $this->blocks($imported));
        self::assertSame(1, $imported->report->count(Omitted::InternalLink));
    }

    public function testFieldsInsideFieldsKeepTheOuterLink(): void
    {
        $imported = $this->import(Docx::body(self::p(
            '<w:r><w:fldChar w:fldCharType="begin"/><w:instrText>HYPERLINK "https://outer.test/"</w:instrText><w:fldChar w:fldCharType="separate"/></w:r>'
            .'<w:r><w:fldChar w:fldCharType="begin"/><w:instrText>PAGE</w:instrText><w:fldChar w:fldCharType="separate"/><w:t>7</w:t><w:fldChar w:fldCharType="end"/></w:r>'
            .'<w:r><w:fldChar w:fldCharType="end"/></w:r>',
        )));

        self::assertSame([['paragraph', ['text' => '<a href="https://outer.test/">7</a>']]], $this->blocks($imported));
    }

    public function testTablesOfContentsBecomeTocBlocks(): void
    {
        $imported = $this->import(Docx::body(
            self::p('<w:r><w:t>Before</w:t><w:fldChar w:fldCharType="begin"/><w:instrText> TOC \o "1-3" </w:instrText><w:fldChar w:fldCharType="separate"/></w:r><w:r><w:t>Entry 1</w:t></w:r>')
            .self::p(self::r('Entry 2'))
            .self::p('<w:r><w:t>Entry 3</w:t><w:fldChar w:fldCharType="end"/></w:r>')
            .self::p(self::r('After'))
            .'<w:sdt><w:sdtPr><w:docPartObj><w:docPartGallery w:val="Table of Contents"/></w:docPartObj></w:sdtPr><w:sdtContent>'.self::p(self::r('Generated')).'</w:sdtContent></w:sdt>'
            .'<w:sdt><w:sdtPr><w:alias w:val="Box"/></w:sdtPr><w:sdtContent>'.self::p(self::r('Controlled')).'</w:sdtContent></w:sdt>'
            .'<w:customXml>'.self::p(self::r('Custom')).'</w:customXml>'
            .'<w:ins>'.self::p(self::r('Inserted')).'</w:ins><w:del>'.self::p(self::r('Deleted')).'</w:del><w:sectPr/>',
        ));

        self::assertSame([
            ['paragraph', ['text' => 'Before']],
            ['toc', ['title' => '', 'depth' => 3, 'wide' => false]],
            ['paragraph', ['text' => 'After']],
            ['toc', ['title' => '', 'depth' => 3, 'wide' => false]],
            ['paragraph', ['text' => 'Controlled']],
            ['paragraph', ['text' => 'Custom']],
            ['paragraph', ['text' => 'Inserted']],
        ], $this->blocks($imported));
        self::assertSame(1, $imported->report->count(Omitted::TrackedChange));
    }

    public function testInlineRevisionsAndControlsKeepTheCurrentText(): void
    {
        $imported = $this->import(Docx::body(self::p(
            '<w:ins><w:r><w:t>new</w:t></w:r></w:ins><w:del><w:r><w:delText>old</w:delText></w:r></w:del><w:moveFrom><w:r><w:t>moved</w:t></w:r></w:moveFrom>'
            .'<w:smartTag><w:r><w:t> tag</w:t></w:r></w:smartTag><w:customXml><w:r><w:t> xml</w:t></w:r></w:customXml>'
            .'<w:sdt><w:sdtPr/><w:sdtContent><w:r><w:t> control</w:t></w:r></w:sdtContent></w:sdt>'
            .'<w:bdo><w:r><w:t> bdo</w:t></w:r></w:bdo><w:dir><w:r><w:t> dir</w:t></w:r></w:dir>'
            .'<w:proofErr/><w:bookmarkStart w:id="1"/><m:oMath><m:r><m:t>x</m:t></m:r></m:oMath><m:oMathPara/>'
            .'<w:r><w:commentReference w:id="1"/><w:object/></w:r>'
            .'<mc:AlternateContent><mc:Choice><w:r><w:t> choice</w:t></w:r></mc:Choice><mc:Fallback><w:r><w:t> fallback</w:t></w:r></mc:Fallback></mc:AlternateContent>',
        )));

        self::assertSame([['paragraph', ['text' => 'new tag xml control bdo dir choice']]], $this->blocks($imported));
        self::assertSame([
            ['tracked-change', 'deletion', 2],
            ['equation', '', 2],
            ['comment', '', 1],
            ['embedded-object', '', 1],
        ], $this->report($imported));
    }

    public function testHiddenTextAndDirectFormattingAreReported(): void
    {
        $imported = $this->import(Docx::body(self::p(
            self::r('shown').self::r(' hidden', '<w:vanish/>').self::r(' not hidden', '<w:vanish w:val="0"/>')
            .self::r(' red', '<w:color w:val="FF0000"/>').self::r(' auto', '<w:color w:val="auto"/>')
            .self::r(' font', '<w:rFonts w:ascii="Times"/>').self::r(' size', '<w:sz w:val="28"/>'),
        )));

        self::assertSame([['paragraph', ['text' => 'shown not hidden red auto font size']]], $this->blocks($imported));
        self::assertSame([['hidden-text', '', 1], ['formatting', 'colour', 1], ['formatting', 'font', 1], ['formatting', 'size', 1]], $this->report($imported));
    }

    public function testCharacterStylesAddTheirFormatting(): void
    {
        $imported = $this->import(Docx::body(self::p(
            self::r('strong', '<w:rStyle w:val="Strong"/>').self::r(' code', '<w:rStyle w:val="VerbatimChar"/>')
            .self::r(' html', '<w:rStyle w:val="HTMLCode"/>').self::r(' src', '<w:rStyle w:val="SourceChar"/>')
            .self::r(' c', '<w:rStyle w:val="CodeChar"/>').self::r(' derived', '<w:rStyle w:val="Derived"/>').self::r(' unknown', '<w:rStyle w:val="Nope"/>'),
        ))->styles(
            $this->style('Strong', 'Strong', '<w:rPr><w:b/></w:rPr>', 'character').$this->style('VerbatimChar', 'Verbatim Char', '', 'character')
            .$this->style('HTMLCode', 'HTML Code', '', 'character').$this->style('SourceChar', 'Source Code Char', '', 'character')
            .$this->style('CodeChar', 'Code Char', '', 'character').$this->style('Derived', 'Derived', '<w:basedOn w:val="Strong"/><w:rPr><w:i/></w:rPr>', 'character'),
        ));

        self::assertSame([['paragraph', ['text' => '<b>strong</b><code class="inline-code"> code html src c</code><b><i> derived</i></b> unknown']]], $this->blocks($imported));
    }

    public function testPicturesBecomeImageBlocksAndTheirBytesMedia(): void
    {
        $imported = $this->import(Docx::body(self::p(
            self::r('Before').'<w:r>'.$this->drawing('rIdImg', 'descr="A dot" title="Dot"').'</w:r>'.self::r('After')
            .'<w:r>'.$this->drawing('rIdImg', 'title="Titled"').'</w:r>'
            .'<w:r>'.$this->drawing('rIdImg', '').'</w:r>'
            .'<w:r><w:pict><v:shape alt="Old"><v:imagedata r:id="rIdImg"/></v:shape></w:pict></w:r>'
            .'<w:r><w:pict><v:imagedata r:id="rIdImg"/></w:pict></w:r>'
            .'<w:r>'.$this->drawing('rIdRemote', '').$this->drawing('rIdFile', '').$this->drawing('rIdEmf', '').$this->drawing('rIdGone', '').$this->drawing('rIdNone', '').'</w:r>'
            .'<w:r><mc:AlternateContent><mc:Choice>'.$this->drawing('rIdImg', 'descr="Chosen"').'</mc:Choice></mc:AlternateContent></w:r>',
        ))->part('image', 'media/image1.png', Png::bytes(), 'rIdImg')
            ->relationship('rIdRemote', 'image', 'https://images.test/a.png', true)
            ->relationship('rIdFile', 'image', 'file:///C:/a.png', true)
            ->part('image', 'media/image2.emf', str_repeat("\0", 40).' EMF', 'rIdEmf')
            ->relationship('rIdGone', 'image', 'media/gone.png'));

        $image = static fn (string $url, string $alt): array => ['image', ['file' => ['url' => $url], 'caption' => '', ...'' === $alt ? [] : ['alt' => $alt], 'withBorder' => false, 'withBackground' => false, 'stretched' => false]];

        self::assertSame([
            ['paragraph', ['text' => 'Before']],
            $image('media/image1.png', 'A dot'),
            ['paragraph', ['text' => 'After']],
            $image('media/image1.png', 'Titled'),
            $image('media/image1.png', ''),
            $image('media/image1.png', 'Old'),
            $image('media/image1.png', ''),
            $image('https://images.test/a.png', ''),
            $image('media/image1.png', 'Chosen'),
        ], $this->blocks($imported));
        self::assertSame(['media/image1.png'], array_keys($imported->media));
        self::assertSame('word/media/image1.png', $imported->media['media/image1.png']->path);
        self::assertSame([['image', 'linked file', 1], ['image', 'image/emf', 1], ['image', 'missing', 2]], $this->report($imported));
    }

    public function testTheDescriptionOfAPictureIsItsTextAlternativeAsWrittenAndCut(): void
    {
        $imported = $this->import(Docx::body(self::p(
            '<w:r>'.$this->drawing('rIdImg', 'descr="Tea &amp; &lt;b&gt;cake&lt;/b&gt;"').'</w:r>'
            .'<w:r>'.$this->drawing('rIdImg', 'descr="'.str_repeat('é', 1001).'"').'</w:r>',
        ))->part('image', 'media/image1.png', Png::bytes(), 'rIdImg'));

        self::assertSame(
            [['Tea & <b>cake</b>', ''], [str_repeat('é', 1000), '']],
            array_map(static fn (Block $block): array => [$block->data['alt'] ?? null, $block->data['caption'] ?? null], $imported->document->blocks),
        );
    }

    public function testShapesChartsTextBoxesAndObjectsAreReported(): void
    {
        $imported = $this->import(Docx::body(self::p(
            self::r('Text').'<w:r><w:drawing><wp:inline><a:graphic><a:graphicData><c:chart r:id="rIdChart"/></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>'
            .'<w:r><w:drawing><wp:anchor><a:graphic><a:graphicData><w:txbxContent>'.self::p(self::r('boxed')).'</w:txbxContent></a:graphicData></a:graphic></wp:anchor></w:drawing></w:r>'
            .'<w:r><w:drawing><wp:anchor><a:graphic/></wp:anchor></w:drawing></w:r>'
            .'<w:r><w:pict><v:rect/></w:pict><w:pict><v:shape><v:textbox><w:txbxContent/></v:textbox></v:shape></w:pict></w:r>',
        )));

        self::assertSame([['paragraph', ['text' => 'Text']]], $this->blocks($imported));
        self::assertSame([['drawing', 'chart', 1], ['drawing', 'shape', 2], ['text-box', '', 2]], $this->report($imported));
    }

    public function testFootnotesAndEndnotesAreNumberedAndWrittenAtTheEnd(): void
    {
        $note = static fn (string $name, string $id, string $content): string => '<w:'.$name.' w:id="'.$id.'">'.$content.'</w:'.$name.'>';
        $imported = $this->import(Docx::body(self::p(
            self::r('Text').'<w:r><w:footnoteReference w:id="2"/></w:r>'.self::r(' more').'<w:r><w:footnoteReference w:id="3"/><w:endnoteReference w:id="1"/><w:footnoteReference w:id="9"/></w:r>',
        ))->footnotes(
            $note('footnote', '0', self::p('<w:r><w:separator/></w:r>'))
            .$note('footnote', '2', self::p('<w:r><w:footnoteRef/></w:r>'.self::r(' First ').self::r('note', '<w:i/>')).self::p('').self::p(self::r('Second line')))
            .$note('footnote', '3', self::p(self::r('With picture').'<w:r>'.$this->drawing('rIdImg', '').'</w:r>').'<w:tbl><w:tr><w:tc>'.self::p(self::r('cell')).'</w:tc><w:tc>'.self::p(self::r('two')).'</w:tc></w:tr></w:tbl><w:sdt><w:sdtContent>'.self::p(self::r('in control')).'</w:sdtContent></w:sdt><w:bookmarkEnd/>'),
        )->endnotes($note('endnote', '1', self::p(self::r('End'))))->part('image', 'media/image1.png', Png::bytes(), 'rIdImg'));

        self::assertSame([
            ['paragraph', ['text' => 'Text<sup data-footnote="fn-1"><a href="#footnote-fn-1">1</a></sup> more<sup data-footnote="fn-2"><a href="#footnote-fn-2">2</a></sup><sup data-footnote="en-1"><a href="#footnote-en-1">1</a></sup>']],
            ['image', ['file' => ['url' => 'media/image1.png'], 'caption' => '', 'withBorder' => false, 'withBackground' => false, 'stretched' => false]],
            ['footnote', ['id' => 'fn-1', 'text' => 'First <i>note</i><br>Second line']],
            ['footnote', ['id' => 'fn-2', 'text' => 'With picture<br>cell two<br>in control']],
            ['footnote', ['id' => 'en-1', 'text' => 'End']],
        ], $this->blocks($imported));
        self::assertSame([['nested-table', '', 1]], $this->report($imported));
    }

    public function testTablesKeepTheirCellsAndSplitMergedOnes(): void
    {
        $cell = static fn (string $text, string $properties = ''): string => '<w:tc><w:tcPr>'.$properties.'</w:tcPr>'.self::p(self::r($text)).'</w:tc>';
        $imported = $this->import(Docx::body(
            '<w:tbl><w:tblPr/><w:tr><w:trPr><w:tblHeader/></w:trPr>'.$cell('Name').$cell('Value').$cell('Note').'</w:tr>'
            .'<w:tr>'.$cell('wide', '<w:gridSpan w:val="2"/>').$cell('tall', '<w:vMerge w:val="restart"/>').'</w:tr>'
            .'<w:tr>'.$cell('a').$cell('b').$cell('under', '<w:vMerge/>').'</w:tr>'
            .'<w:tr><w:tc>'.self::p(self::r('two')).self::p('').self::p(self::r('lines').'<w:r>'.$this->drawing('rIdImg', '').'</w:r>').'</w:tc></w:tr>'
            .'<w:tr><w:trPr><w:tblHeader/></w:trPr>'.$cell('late header').'</w:tr></w:tbl>'
            .'<w:tbl><w:tr>'.$cell('plain').'</w:tr></w:tbl><w:tbl><w:tblPr/></w:tbl>',
        )->part('image', 'media/image1.png', Png::bytes(), 'rIdImg'));

        self::assertSame([
            ['table', ['withHeadings' => true, 'stretched' => false, 'content' => [
                ['Name', 'Value', 'Note'],
                ['wide', '', 'tall'],
                ['a', 'b', ''],
                ['two<br>lines', '', ''],
                ['late header', '', ''],
            ]]],
            ['image', ['file' => ['url' => 'media/image1.png'], 'caption' => '', 'withBorder' => false, 'withBackground' => false, 'stretched' => false]],
            ['table', ['withHeadings' => false, 'stretched' => false, 'content' => [['plain']]]],
        ], $this->blocks($imported));
        self::assertSame([['merged-cell', '', 2]], $this->report($imported));
    }

    public function testHeadersFootersAndMacrosAreReported(): void
    {
        $part = static fn (string $name, string $text): string => '<w:'.$name.' '.Docx::NAMESPACES.'>'.self::p(self::r($text)).'</w:'.$name.'>';
        $imported = $this->import(Docx::body(self::p(self::r('Body')))
            ->part('header', 'header1.xml', $part('hdr', 'Running head'), 'rIdH1')
            ->part('header', 'header2.xml', $part('hdr', ' '), 'rIdH2')
            ->part('footer', 'footer1.xml', $part('ftr', 'Page'), 'rIdF1')
            ->relationship('rIdF2', 'footer', 'footer9.xml')
            ->part('vbaProject', 'vbaProject.bin', 'macro', 'rIdVba'));

        self::assertSame([['header-footer', 'header', 1], ['header-footer', 'footer', 1], ['macro', '', 1]], $this->report($imported));
    }

    public function testCorePropertiesAreTheMetadata(): void
    {
        $imported = $this->import(Docx::body(self::p(self::r('Body')))->core(
            '<dc:title>The Title</dc:title><dc:subject>Subject</dc:subject><dc:creator>Ann</dc:creator><cp:keywords>a, b</cp:keywords>'
            .'<dc:description>About</dc:description><dc:language>fr-CA</dc:language><dcterms:created>2026-01-02T03:04:05Z</dcterms:created>'
            .'<dcterms:modified>2026-02-03T04:05:06Z</dcterms:modified><cp:lastModifiedBy>Bob</cp:lastModifiedBy><dc:title> </dc:title>',
        ));

        self::assertSame(['title' => 'The Title', 'subject' => 'Subject', 'creator' => 'Ann', 'keywords' => 'a, b', 'description' => 'About', 'language' => 'fr-CA', 'created' => '2026-01-02T03:04:05Z', 'modified' => '2026-02-03T04:05:06Z'], $imported->metadata);
        self::assertSame([], $this->import(Docx::body(''))->metadata);
    }

    public function testAMainPartInASubfolderResolvesItsRelationships(): void
    {
        $bytes = Zip::of([
            '_rels/.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="a" Type="x/officeDocument" Target="/doc/main/document.xml"/></Relationships>',
            'doc/main/document.xml' => '<w:document '.Docx::NAMESPACES.'><w:body>'.self::p('<w:r>'.$this->drawing('img', '').'</w:r>').'</w:body></w:document>',
            'doc/main/_rels/document.xml.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="img" Type="x/image" Target="../media/./dot.png"/></Relationships>',
            'doc/media/dot.png' => Png::bytes(),
        ]);

        $imported = new DocumentImporter()->import($bytes, Format::Docx);

        self::assertSame('doc/media/dot.png', $imported->media['media/dot.png']->path);
    }

    /**
     * @return iterable<string, array{string, array<string, string>}>
     */
    public static function brokenPackages(): iterable
    {
        $rels = static fn (string $target): string => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="a" Type="x/officeDocument" Target="'.$target.'"/></Relationships>';

        yield 'no package relationships' => ['The archive has no main document part: it is not a word processing document.', ['word/document.xml' => '<x/>']];
        yield 'external main part' => ['The archive has no main document part: it is not a word processing document.', ['_rels/.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="a" Type="x/officeDocument" Target="https://a.test/d.xml" TargetMode="External"/></Relationships>']];
        yield 'missing main part' => ['The part "word/document.xml" has no document body.', ['_rels/.rels' => $rels('word/document.xml')]];
        yield 'main part without body' => ['The part "word/document.xml" has no document body.', ['_rels/.rels' => $rels('word/document.xml'), 'word/document.xml' => '<w:document '.Docx::NAMESPACES.'/>']];
        yield 'malformed XML' => ['The entry "word/document.xml" is not well-formed XML.', ['_rels/.rels' => $rels('word/document.xml'), 'word/document.xml' => '<w:document']];
        yield 'document type' => ['The entry "word/document.xml" declares a document type, which no office document does.', ['_rels/.rels' => $rels('word/document.xml'), 'word/document.xml' => '<!DOCTYPE w [<!ENTITY e "x">]><w:document '.Docx::NAMESPACES.'><w:body/></w:document>']];
    }

    /**
     * @param array<string, string> $entries
     */
    #[DataProvider('brokenPackages')]
    public function testBrokenPackagesAreRefused(string $message, array $entries): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains($message);

        new DocumentImporter()->import(Zip::of($entries), Format::Docx);
    }

    public function testCodeBetweenItemsOfOneListSplitsTheList(): void
    {
        $imported = $this->import(Docx::body($this->item('One', 1, 0).self::p(self::r('code'), '<w:pStyle w:val="Code"/>').$this->item('Two', 1, 0))
            ->numbering($this->abstract('10', '<w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/></w:lvl><w:lvl w:ilvl="1"/>').'<w:num w:numId="1"><w:abstractNumId w:val="10"/></w:num>')
            ->styles($this->style('Code', 'Code')));

        self::assertSame(['list', 'code', 'list'], array_map(static fn (Block $block): string => $block->type, $imported->document->blocks));
        self::assertSame(['b1', 'b2', 'b3'], array_map(static fn (Block $block): string => $block->id, $imported->document->blocks));
    }

    public function testDirectNumberingWinsOverTheStyle(): void
    {
        $imported = $this->import(Docx::body(self::p(self::r('Direct'), '<w:pStyle w:val="Listed"/><w:numPr><w:ilvl w:val="0"/><w:numId w:val="2"/></w:numPr>'))
            ->numbering($this->abstract('10', '<w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/></w:lvl>').$this->abstract('20', '<w:lvl w:ilvl="0"><w:numFmt w:val="decimal"/></w:lvl>')
                .'<w:num w:numId="1"><w:abstractNumId w:val="10"/></w:num><w:num w:numId="2"><w:abstractNumId w:val="20"/></w:num>')
            ->styles($this->style('Listed', 'Listed', '<w:pPr><w:numPr><w:numId w:val="1"/></w:numPr></w:pPr>')));

        self::assertSame('ordered', $imported->document->blocks[0]->data['style']);
    }

    public function testTheInnermostLinkWins(): void
    {
        $field = static fn (string $instruction, string $result): string => '<w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText>'.$instruction.'</w:instrText><w:t>hidden</w:t><w:fldChar w:fldCharType="separate"/></w:r>'.$result.'<w:r><w:fldChar w:fldCharType="end"/></w:r>';
        $imported = $this->import(Docx::body(self::p(
            '<w:fldSimple w:instr="HYPERLINK &quot;https://outer.test/&quot;"><w:hyperlink r:id="rIdLink"><w:r><w:t>a</w:t></w:r></w:hyperlink></w:fldSimple>'
            .'<w:hyperlink r:id="rIdLink"><w:fldSimple w:instr="HYPERLINK &quot;https://inner.test/&quot;"><w:r><w:t>b</w:t></w:r></w:fldSimple></w:hyperlink>'
            .'<w:hyperlink r:id="rIdLink">'.$field('HYPERLINK "https://field.test/"', self::r('c')).'</w:hyperlink>'
            .$field('HYPERLINK "https://outer.test/"', $field('HYPERLINK "https://nested.test/"', self::r('d'))),
        ))->relationship('rIdLink', 'hyperlink', 'https://link.test/', true));

        self::assertSame([['paragraph', ['text' => '<a href="https://link.test/">a</a><a href="https://inner.test/">b</a><a href="https://link.test/">c</a><a href="https://nested.test/">d</a>']]], $this->blocks($imported));
    }

    public function testOnlyTocFieldsHideTheirResult(): void
    {
        $field = static fn (string $instruction, string $result, bool $separate = true): string => '<w:r><w:fldChar w:fldCharType="begin"/><w:instrText>'.$instruction.'</w:instrText>'.($separate ? '<w:fldChar w:fldCharType="separate"/>' : '').'</w:r>'.$result.'<w:r><w:fldChar w:fldCharType="end"/></w:r>';
        $imported = $this->import(Docx::body(self::p(
            $field('PAGE', '', false).$field('DATE', self::r('date')).$field('REF TOC \h', self::r(' ref'))
            .$field('TOC \o', self::r(' first')).self::r(' between').$field(' TOC', self::r(' second')).self::r(' after'),
        )));

        self::assertSame([
            ['paragraph', ['text' => 'date ref']],
            ['toc', ['title' => '', 'depth' => 3, 'wide' => false]],
            ['paragraph', ['text' => 'between']],
            ['toc', ['title' => '', 'depth' => 3, 'wide' => false]],
            ['paragraph', ['text' => 'after']],
        ], $this->blocks($imported));
    }

    public function testHeaderAndFooterPartsAreCountedWhenTheyHoldText(): void
    {
        $part = static fn (string $text): string => '<w:hdr '.Docx::NAMESPACES.'>'.self::p(self::r($text)).'</w:hdr>';
        $imported = $this->import(Docx::body('')->part('header', 'h1.xml', $part('One'), 'h1')->part('header', 'h2.xml', $part('Two'), 'h2')->part('header', 'h3.xml', $part(''), 'h3')
            ->part('footer', 'f1.xml', $part('One'), 'f1')->part('footer', 'f2.xml', $part('Two'), 'f2')->relationship('f3', 'footer', 'gone.xml'));

        self::assertSame([['header-footer', 'header', 2], ['header-footer', 'footer', 2]], $this->report($imported));
    }

    public function testMissingCorePropertiesGiveNoMetadata(): void
    {
        $bytes = Zip::of([
            '_rels/.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="a" Type="x/officeDocument" Target="word/document.xml"/><Relationship Id="b" Type="x/core-properties" Target="docProps/core.xml"/></Relationships>',
            'word/document.xml' => '<w:document '.Docx::NAMESPACES.'><w:body/></w:document>',
        ]);

        self::assertSame([], new DocumentImporter()->import($bytes, Format::Docx)->metadata);
    }

    public function testPictureEdgeCases(): void
    {
        $imported = $this->import(Docx::body(self::p(
            '<w:r><w:drawing><wp:inline><a:graphic><a:graphicData><a:blip r:embed="rIdImg"/></a:graphicData></a:graphic></wp:inline></w:drawing>'
            .$this->drawing('rIdUpper', '').$this->drawing('rIdTricky', '').'</w:r>',
        ))->part('image', 'media/image1.png', Png::bytes(), 'rIdImg')
            ->relationship('rIdUpper', 'image', 'HTTPS://images.test/A.png', true)
            ->relationship('rIdTricky', 'image', 'file:///https://images.test/a.png', true));

        self::assertSame([['url' => 'media/image1.png'], ['url' => 'HTTPS://images.test/A.png']], array_map(static fn (Block $block): mixed => $block->data['file'], $imported->document->blocks));
        self::assertSame([['image', 'linked file', 1]], $this->report($imported));
    }

    public function testSvgImagesOfTablesAndNotesAreSanitizedOnImport(): void
    {
        $hostile = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(2)</script><rect fill="red"/></svg>';
        $imported = $this->import(Docx::body(
            '<w:tbl><w:tr><w:tc>'.self::p('<w:r>'.$this->drawing('rIdCell', 'descr="In a cell"').'</w:r>').'</w:tc></w:tr></w:tbl>'
            .self::p(self::r('Text').'<w:r><w:footnoteReference w:id="1"/></w:r>'),
        )->part('image', 'media/cell.svg', $hostile, 'rIdCell')
            ->footnotes('<w:footnote w:id="1">'.self::p('<w:r>'.$this->drawing('rIdNote', '').'</w:r>').'</w:footnote>')
            ->part('image', 'media/note.svg', $hostile, 'rIdNote'));

        self::assertSame(['media/cell.svg', 'media/note.svg'], array_keys($imported->media));

        foreach ($imported->media as $media) {
            self::assertSame('<svg xmlns="http://www.w3.org/2000/svg"><rect fill="red"/></svg>', $media->contents);
            self::assertSame('image/svg+xml', $media->mediaType);
        }

        self::assertSame([['svg-content', 'event_handler', 2], ['svg-content', 'denied_element', 2]], $this->report($imported));
    }

    public function testNotesAndTablesEdgeCases(): void
    {
        $cell = static fn (string $text, string $properties = ''): string => '<w:tc><w:tcPr>'.$properties.'</w:tcPr>'.self::p(self::r($text)).'</w:tc>';
        $imported = $this->import(Docx::body(
            self::p(self::r('Text').'<w:r><w:footnoteReference w:id="1"/></w:r>')
            .'<w:tbl><w:tr>'.$cell('plain').'</w:tr><w:tr><w:trPr><w:tblHeader/></w:trPr>'.$cell('late', '<w:gridSpan w:val="1"/>').'</w:tr></w:tbl>'
            .'<w:tbl><w:tr>'.$cell('a').'</w:tr><w:tr>'.$cell('b').'</w:tr></w:tbl>'
            .'<w:tbl><w:tr><w:tc><w:tbl><w:tr>'.$cell('').$cell('x').$cell('').'</w:tr></w:tbl></w:tc></w:tr></w:tbl>'
            .'<w:sdt><w:sdtContent>'.self::p(self::r('No properties')).'</w:sdtContent></w:sdt>',
        )->footnotes('<w:footnote w:id="1"><w:customXml>'.self::p(self::r('custom')).'</w:customXml></w:footnote>'));

        self::assertSame([
            ['paragraph', ['text' => 'Text<sup data-footnote="fn-1"><a href="#footnote-fn-1">1</a></sup>']],
            ['table', ['withHeadings' => false, 'stretched' => false, 'content' => [['plain'], ['late']]]],
            ['table', ['withHeadings' => false, 'stretched' => false, 'content' => [['a'], ['b']]]],
            ['table', ['withHeadings' => false, 'stretched' => false, 'content' => [['x']]]],
            ['paragraph', ['text' => 'No properties']],
            ['footnote', ['id' => 'fn-1', 'text' => 'custom']],
        ], $this->blocks($imported));
        self::assertSame([['nested-table', '', 1]], $this->report($imported));
    }

    public function testOnlyExactHeadingNamesMakeHeadings(): void
    {
        $imported = $this->import(Docx::body(
            self::p(self::r('a'), '<w:pStyle w:val="A"/>').self::p(self::r('b'), '<w:pStyle w:val="B"/>').self::p(self::r('c'), '<w:pStyle w:val="C"/>'),
        )->styles($this->style('A', 'subheading 2').$this->style('B', 'heading 23').$this->style('C', 'heading 2&#10;')));

        self::assertSame([['paragraph', ['text' => 'a']], ['paragraph', ['text' => 'b']], ['paragraph', ['text' => 'c']]], $this->blocks($imported));
    }

    public function testTheTableOfContentsControlIsTitledByItsFirstHeading(): void
    {
        $imported = $this->import(Docx::body(
            '<w:sdt><w:sdtPr><w:docPartObj><w:docPartGallery w:val="Table of Contents"/></w:docPartObj></w:sdtPr><w:sdtContent>'
            .self::p(self::r('Intro'), '<w:pStyle w:val="Heading1"/>')
            .self::p(self::r(' Contents & more ', '<w:b/>'), '<w:pStyle w:val="TOCHeading"/>')
            .self::p(self::r('Second'), '<w:pStyle w:val="TOCHeading"/>')
            .self::p(self::r('Entry'))
            .'</w:sdtContent></w:sdt>',
        )->styles($this->style('Heading1', 'heading 1').$this->style('TOCHeading', 'TOC Heading', '<w:basedOn w:val="Heading1"/>')));

        self::assertSame([['toc', ['title' => 'Contents & more', 'depth' => 3, 'wide' => false]]], $this->blocks($imported));
    }

    public function testTheFirstNumberOfAListMayBeOverriddenByItsInstance(): void
    {
        $imported = $this->import(Docx::body($this->item('One', 1, 0))->numbering(
            $this->abstract('7', '<w:lvl w:ilvl="0"><w:start w:val="3"/><w:numFmt w:val="decimal"/></w:lvl>')
            .'<w:num w:numId="1"><w:abstractNumId w:val="7"/><w:lvlOverride w:ilvl="0"><w:startOverride w:val="5"/></w:lvlOverride><w:lvlOverride w:ilvl="1"><w:startOverride w:val="8"/></w:lvlOverride><w:lvlOverride w:ilvl="0"/></w:num>',
        ));

        self::assertSame([['list', ['style' => 'ordered', 'meta' => ['start' => 5], 'items' => [['content' => 'One', 'meta' => [], 'items' => []]]]]], $this->blocks($imported));
    }

    public function testAStyleIsMappedByItsNameBeforeItsIdentifier(): void
    {
        $imported = new DocumentImporter(styles: new StyleMap(['title text' => StyleRole::Heading2, 'chapter' => StyleRole::Quote]))->import(
            Docx::body(self::p(self::r('X'), '<w:pStyle w:val="Chapter"/>'))->styles($this->style('Chapter', 'Title Text'))->bytes(),
            Format::Docx,
        );

        self::assertSame([['header', ['text' => 'X', 'level' => 2]]], $this->blocks($imported));
    }

    public function testAStyleWithoutAnIdentifierIsNeverMapped(): void
    {
        $imported = new DocumentImporter(styles: new StyleMap(['' => StyleRole::Quote]))->import(Docx::body(self::p(self::r('X')))->bytes(), Format::Docx);

        self::assertSame([['paragraph', ['text' => 'X']]], $this->blocks($imported));
    }

    private function import(Docx $docx): Imported
    {
        return new DocumentImporter()->import($docx->bytes(), filename: 'letter.docx');
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

    private static function p(string $content, string $properties = ''): string
    {
        return '<w:p>'.('' === $properties ? '' : '<w:pPr>'.$properties.'</w:pPr>').$content.'</w:p>';
    }

    private static function r(string $text, string $properties = ''): string
    {
        return '<w:r>'.('' === $properties ? '' : '<w:rPr>'.$properties.'</w:rPr>').'<w:t xml:space="preserve">'.htmlspecialchars($text, \ENT_QUOTES).'</w:t></w:r>';
    }

    private function item(string $text, int $list, int $level): string
    {
        return self::p(self::r($text), '<w:numPr><w:ilvl w:val="'.$level.'"/><w:numId w:val="'.$list.'"/></w:numPr>');
    }

    private function abstract(string $id, string $levels): string
    {
        return '<w:abstractNum w:abstractNumId="'.$id.'">'.$levels.'</w:abstractNum>';
    }

    private function style(string $id, string $name, string $content = '', string $type = 'paragraph'): string
    {
        return '<w:style w:type="'.$type.'" w:styleId="'.$id.'"><w:name w:val="'.$name.'"/>'.$content.'</w:style>';
    }

    private function drawing(string $id, string $properties): string
    {
        return '<w:drawing><wp:inline><wp:docPr id="1" name="Picture" '.$properties.'/><a:graphic><a:graphicData><a:blip r:embed="'.$id.'"/></a:graphicData></a:graphic></wp:inline></w:drawing>';
    }
}
