<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit;

use Phil\DocumentImport\DocumentImporter;
use Phil\DocumentImport\Exception\InvalidDocument;
use Phil\DocumentImport\Format;
use Phil\DocumentImport\Imported;
use Phil\DocumentImport\Tests\Unit\Fixture\Odt;
use Phil\DocumentImport\Tests\Unit\Fixture\Png;
use Phil\DocumentImport\Tests\Unit\Fixture\Zip;
use Phil\Ujo\Block;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocumentImporter::class)]
final class OdtImportTest extends TestCase
{
    public function testSvgImagesOfTablesAndNotesAreSanitizedOnImport(): void
    {
        $hostile = '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><p>x</p></foreignObject><a href="javascript:alert(1)"><rect/></a></svg>';
        $frame = static fn (string $href): string => '<draw:frame><draw:image xlink:href="'.$href.'"/></draw:frame>';
        $imported = new DocumentImporter()->import(Odt::text(
            '<table:table><table:table-row><table:table-cell><text:p>Cell'.$frame('Pictures/cell.svg').'</text:p></table:table-cell></table:table-row></table:table>'
            .'<text:p>Text<text:note text:note-class="footnote"><text:note-body><text:p>Note'.$frame('Pictures/note.svg').'</text:p></text:note-body></text:note></text:p>',
        )->file('Pictures/cell.svg', $hostile)->file('Pictures/note.svg', $hostile)->bytes(), Format::Odt);

        self::assertSame(['media/cell.svg', 'media/note.svg'], array_keys($imported->media));

        foreach ($imported->media as $media) {
            self::assertStringNotContainsString('javascript', $media->contents);
            self::assertStringNotContainsString('foreignObject', $media->contents);
            self::assertStringContainsString('<rect/>', $media->contents);
        }

        self::assertContains('svg-content', array_column($imported->report->toArray(), 'what'));
    }

    public function testSpansKeepTheCharacterFormattingUjoHas(): void
    {
        $imported = $this->import(Odt::text(
            '<text:p>plain <text:span text:style-name="B">bold <text:span text:style-name="I">italic</text:span></text:span>'
            .'<text:span text:style-name="W">heavy</text:span><text:span text:style-name="W4"> light</text:span>'
            .'<text:span text:style-name="O"> oblique</text:span><text:span text:style-name="U"> u</text:span><text:span text:style-name="UN"> nu</text:span>'
            .'<text:span text:style-name="S"> s</text:span><text:span text:style-name="SN"> ns</text:span>'
            .'<text:span text:style-name="M"> m</text:span><text:span text:style-name="MT"> t</text:span>'
            .'<text:span text:style-name="Sup">2</text:span><text:span text:style-name="SupP">3</text:span>'
            .'<text:span text:style-name="Sub">i</text:span><text:span text:style-name="SubP">j</text:span><text:span text:style-name="Base"> b</text:span>'
            .'<text:span text:style-name="Child"> child</text:span><text:span text:style-name="Source_20_Text"> code</text:span>'
            .'<text:span text:style-name="Teletype"> tt</text:span><text:span text:style-name="Ex"> ex</text:span><text:span text:style-name="Nope"> none</text:span>'
            .'<text:span text:style-name="Loop"> loop</text:span></text:p>',
        )->automatic(
            $this->text('B', 'fo:font-weight="bold"').$this->text('I', 'fo:font-style="italic"').$this->text('W', 'fo:font-weight="700"')
            .$this->text('W4', 'fo:font-weight="400"').$this->text('O', 'fo:font-style="oblique"')
            .$this->text('U', 'style:text-underline-style="solid"').$this->text('UN', 'style:text-underline-style="none"')
            .$this->text('S', 'style:text-line-through-style="solid"').$this->text('SN', 'style:text-line-through-style="none"')
            .$this->text('M', 'fo:background-color="#ffff00"').$this->text('MT', 'fo:background-color="transparent"')
            .$this->text('Sup', 'style:text-position="super 58%"').$this->text('SupP', 'style:text-position="33% 58%"')
            .$this->text('Sub', 'style:text-position="sub 58%"').$this->text('SubP', 'style:text-position="-33% 58%"')
            .$this->text('Base', 'style:text-position="0% 100%"').$this->text('Child', '', 'B')
            .$this->text('Loop', 'fo:font-style="italic"', 'Loop'),
        )->styles(
            '<style:style style:name="Source_20_Text" style:family="text"/><style:style style:name="Teletype" style:family="text"/>'
            .'<style:style style:name="Ex" style:display-name="Example" style:family="text"/>',
        ));

        self::assertSame([['paragraph', ['text' => 'plain <b>bold <i>italic</i>heavy</b> light<i> oblique</i><u class="cdx-underline"> u</u> nu<s class="cdx-strikethrough"> s</s> ns<mark class="cdx-marker"> m</mark> t<sup>23</sup><sub>ij</sub> b<b> child</b><code class="inline-code"> code tt ex</code> none<i> loop</i>']]], $this->blocks($imported));
        self::assertTrue($imported->report->isEmpty());
        self::assertSame(Format::Odt, $imported->format);
    }

    public function testParagraphsHeadingsQuotationsAndCode(): void
    {
        $imported = $this->import(Odt::text(
            '<text:h text:outline-level="2">Two</text:h><text:h>Default</text:h><text:h text:outline-level="9">Nine</text:h>'
            .'<text:p text:style-name="Title">Book</text:p><text:p text:style-name="Q">Said</text:p><text:p text:style-name="Quote">Too</text:p>'
            .'<text:p text:style-name="Preformatted_20_Text">a  <text:s text:c="3"/>b<text:s/>c<text:tab/>d</text:p><text:p text:style-name="Src">x &lt; y</text:p>'
            .'<text:p text:style-name="Bold">Direct bold</text:p><text:h text:style-name="Bold" text:outline-level="1">Bold heading</text:h>'
            .'<text:p>line<text:line-break/>break'."\n\t".'joined</text:p><text:p/><text:p> <text:s/> </text:p>'
            .'<text:p>a<text:soft-page-break/>b<text:bookmark text:name="x"/>c<text:sequence>1</text:sequence><text:date>today</text:date></text:p>'
            .'<text:p text:style-name="Centered">middle</text:p><text:p text:style-name="Righted">right</text:p><text:p text:style-name="Ended">end</text:p>'
            .'<text:p text:style-name="Justified">just</text:p><text:p text:style-name="Lefted">left</text:p>',
        )->automatic(
            '<style:style style:name="Q" style:family="paragraph" style:parent-style-name="Quotations"/>'
            .'<style:style style:name="Bold" style:family="paragraph"><style:text-properties fo:font-weight="bold"/></style:style>'
            .$this->paragraph('Centered', 'fo:text-align="center"').$this->paragraph('Righted', 'fo:text-align="right"')
            .$this->paragraph('Ended', 'fo:text-align="end"').$this->paragraph('Justified', 'fo:text-align="justify"')
            .'<style:style style:name="Lefted" style:family="paragraph" style:parent-style-name="Base"><style:paragraph-properties fo:text-align="start"/></style:style>',
        )->styles(
            '<style:style style:name="Title" style:family="paragraph"/><style:style style:name="Quotations" style:family="paragraph"/>'
            .'<style:style style:name="Quote" style:display-name="quote" style:family="paragraph"/>'
            .'<style:style style:name="Preformatted_20_Text" style:family="paragraph"/>'
            .'<style:style style:name="Src" style:display-name="Source Text" style:family="paragraph"/>'
            .'<style:style style:name="Base" style:family="paragraph"><style:paragraph-properties fo:text-align="center"/></style:style>',
        ));

        self::assertSame([
            ['header', ['text' => 'Two', 'level' => 2]],
            ['header', ['text' => 'Default', 'level' => 1]],
            ['header', ['text' => 'Nine', 'level' => 6]],
            ['header', ['text' => 'Book', 'level' => 1]],
            ['quote', ['text' => 'Said', 'caption' => '', 'alignment' => 'left']],
            ['quote', ['text' => 'Too', 'caption' => '', 'alignment' => 'left']],
            ['code', ['code' => "a     b c d\nx < y"]],
            ['paragraph', ['text' => '<b>Direct bold</b>']],
            ['header', ['text' => '<b>Bold heading</b>', 'level' => 1]],
            ['paragraph', ['text' => 'line<br>break  joined']],
            ['paragraph', ['text' => 'abc1today']],
            ['paragraph', ['text' => 'middle']],
            ['paragraph', ['text' => 'right']],
            ['paragraph', ['text' => 'end']],
            ['paragraph', ['text' => 'just']],
            ['paragraph', ['text' => 'left']],
        ], $this->blocks($imported));
        self::assertSame([null, null, null, null, null, null, null, null, null, null, null, 'center', 'right', 'right', 'justify', null], array_map($this->alignment(...), $imported->document->blocks));
    }

    public function testPageBreaksBeforeParagraphs(): void
    {
        $imported = $this->import(Odt::text('<text:p>one</text:p><text:p text:style-name="Break">two</text:p><text:h text:style-name="Child">three</text:h>')->automatic(
            $this->paragraph('Break', 'fo:break-before="page"').'<style:style style:name="Child" style:family="paragraph" style:parent-style-name="Break"/>'.$this->paragraph('Auto', 'fo:break-before="auto"'),
        ));

        self::assertSame([['paragraph', ['text' => 'one']], ['pageBreak', []], ['paragraph', ['text' => 'two']], ['pageBreak', []], ['header', ['text' => 'three', 'level' => 1]]], $this->blocks($imported));
    }

    public function testListsBecomeNestedListBlocks(): void
    {
        $imported = $this->import(Odt::text(
            '<text:list text:style-name="Numbered"><text:list-item><text:p>One</text:p><text:p>continued</text:p>'
            .'<text:list><text:list-item><text:p>One.a</text:p></text:list-item><text:list-item><text:list><text:list-item><text:p>Deep</text:p></text:list-item></text:list></text:list-item></text:list></text:list-item>'
            .'<text:list-header><text:p>Header</text:p></text:list-header><text:list-item><text:p>Two</text:p><text:p/></text:list-item></text:list>'
            .'<text:list text:style-name="Bullets"><text:list-item><text:p>Bullet</text:p></text:list-item></text:list>'
            .'<text:list text:style-name="Roman"><text:list-item><text:h>Roman</text:h></text:list-item></text:list>'
            .'<text:list text:style-name="Letters"><text:list-item><text:p>Letter</text:p></text:list-item></text:list>'
            .'<text:list text:style-name="Undefined"><text:list-item><text:p>Plain</text:p></text:list-item></text:list>'
            .'<text:list text:style-name="DeepOnly"><text:list-item><text:p>Deep only</text:p></text:list-item></text:list>'
            .'<text:list><text:list-item><text:p>No style</text:p></text:list-item></text:list>',
        )->automatic(
            '<text:list-style style:name="Numbered"><text:list-level-style-bullet text:level="2"/><text:list-level-style-number text:level="1" style:num-format="1" text:start-value="4"/></text:list-style>'
            .'<text:list-style style:name="Bullets"><text:list-level-style-bullet text:level="1"/></text:list-style>'
            .'<text:list-style style:name="Roman"><text:list-level-style-number text:level="1" style:num-format="I" text:start-value="1"/></text:list-style>'
            .'<text:list-style style:name="Letters"><text:list-level-style-number text:level="1" style:num-format="a"/></text:list-style>'
            .'<text:list-style style:name="DeepOnly"><text:list-level-style-number text:level="2"/></text:list-style>',
        ));

        $item = static fn (string $content, array $items = []): array => ['content' => $content, 'meta' => [], 'items' => $items];

        self::assertSame([
            ['list', ['style' => 'ordered', 'meta' => ['start' => 4], 'items' => [$item('One<br>continued', [$item('One.a', [$item('Deep')])]), $item('Header'), $item('Two')]]],
            ['list', ['style' => 'unordered', 'meta' => [], 'items' => [$item('Bullet')]]],
            ['list', ['style' => 'ordered', 'meta' => ['counterType' => 'upper-roman'], 'items' => [$item('Roman')]]],
            ['list', ['style' => 'ordered', 'meta' => ['counterType' => 'lower-alpha'], 'items' => [$item('Letter')]]],
            ['list', ['style' => 'unordered', 'meta' => [], 'items' => [$item('Plain')]]],
            ['list', ['style' => 'unordered', 'meta' => [], 'items' => [$item('Deep only')]]],
            ['list', ['style' => 'unordered', 'meta' => [], 'items' => [$item('No style')]]],
        ], $this->blocks($imported));
    }

    public function testLinksNotesAndComments(): void
    {
        $imported = $this->import(Odt::text(
            '<text:p><text:a xlink:href="https://example.com/?a=1&amp;b=2">site</text:a> <text:a xlink:href="#Heading">inside</text:a>'
            .'<text:note text:note-class="footnote"><text:note-citation>1</text:note-citation><text:note-body><text:p>First <text:span text:style-name="I">note</text:span></text:p><text:p/><text:p>line</text:p></text:note-body></text:note>'
            .'<text:note text:note-class="endnote"><text:note-citation>i</text:note-citation><text:note-body><text:p>End</text:p><text:list><text:list-item><text:p>listed</text:p></text:list-item></text:list><table:table><table:table-row><table:table-cell><text:p>cell</text:p></table:table-cell><table:table-cell><text:p>two</text:p></table:table-cell></table:table-row></table:table><text:section><text:p>sectioned</text:p></text:section><text:p><draw:frame><draw:image xlink:href="Pictures/a.png"/></draw:frame></text:p></text:note-body></text:note>'
            .'<office:annotation><text:p>comment</text:p></office:annotation>after<office:annotation-end/><other:x xmlns:other="urn:other">gone</other:x></text:p>',
        )->automatic($this->text('I', 'fo:font-style="italic"')));

        self::assertSame([
            ['paragraph', ['text' => '<a href="https://example.com/?a=1&amp;b=2">site</a> inside<sup data-footnote="fn-1"><a href="#footnote-fn-1">1</a></sup><sup data-footnote="en-1"><a href="#footnote-en-1">1</a></sup>after']],
            ['footnote', ['id' => 'fn-1', 'text' => 'First <i>note</i><br>line']],
            ['footnote', ['id' => 'en-1', 'text' => 'End<br>listed<br>cell two<br>sectioned']],
        ], $this->blocks($imported));
        self::assertSame([['internal-link', '', 1], ['nested-table', '', 1], ['image', 'missing', 1], ['comment', '', 1]], $this->report($imported));
    }

    public function testFramesBecomeImagesOrAreReported(): void
    {
        $frame = static fn (string $content, string $alt = ''): string => '<draw:frame draw:name="f">'.$alt.$content.'</draw:frame>';
        $imported = $this->import(Odt::text(
            '<text:p>Before'.$frame('<draw:image xlink:href="Pictures/dot.png"/>', '<svg:title>Dot</svg:title><svg:desc>A dot</svg:desc>').'After</text:p>'
            .'<text:p>'.$frame('<draw:image xlink:href="./Pictures/dot.png"/>', '<svg:title>Titled</svg:title>')
            .$frame('<draw:image xlink:href="https://images.test/a.png"/>').$frame('<draw:image xlink:href="Pictures/gone.png"/>')
            .$frame('<draw:image><office:binary-data>'.chunk_split(Png::BASE64, 20, "\n").'</office:binary-data></draw:image>')
            .$frame('<draw:image><office:binary-data>!!!</office:binary-data></draw:image>')
            .$frame('<draw:image xlink:href="Pictures/a.emf"/>')
            .$frame('<draw:text-box><text:p>boxed</text:p></draw:text-box>').$frame('<draw:object xlink:href="./Object 1"/><draw:image xlink:href="Pictures/dot.png"/>')
            .$frame('<draw:object-ole/>').$frame('<draw:plugin/>').$frame('').'<draw:a xlink:href="https://x.test/">'.$frame('<draw:image xlink:href="Pictures/dot.png"/>').'</draw:a>'
            .'<draw:custom-shape/><draw:rect/></text:p>'
            .$frame('<draw:image xlink:href="Pictures/dot.png"/>', '<svg:desc>On the page</svg:desc>'),
        )->file('Pictures/dot.png', Png::bytes())->file('Pictures/a.emf', str_repeat("\0", 40).' EMF'));

        $image = static fn (string $url, string $alt): array => ['image', ['file' => ['url' => $url], 'caption' => '', ...'' === $alt ? [] : ['alt' => $alt], 'withBorder' => false, 'withBackground' => false, 'stretched' => false]];

        self::assertSame([
            ['paragraph', ['text' => 'Before']],
            $image('media/dot.png', 'A dot'),
            ['paragraph', ['text' => 'After']],
            $image('media/dot.png', 'Titled'),
            $image('https://images.test/a.png', ''),
            $image('media/image.png', ''),
            $image('media/dot.png', ''),
            $image('media/dot.png', 'On the page'),
        ], $this->blocks($imported));
        self::assertSame(['media/dot.png', 'media/image.png'], array_keys($imported->media));
        self::assertSame([['image', 'missing', 2], ['image', 'image/emf', 1], ['text-box', '', 1], ['embedded-object', '', 2], ['drawing', 'shape', 4]], $this->report($imported));
    }

    public function testTablesKeepTheirCellsAndSplitMergedOnes(): void
    {
        $cell = static fn (string $text, string $attributes = ''): string => '<table:table-cell '.$attributes.'><text:p>'.$text.'</text:p></table:table-cell>';
        $imported = $this->import(Odt::text(
            '<table:table><table:table-column/><table:table-header-rows><table:table-row>'.$cell('Name').$cell('Value').$cell('Note').'</table:table-row></table:table-header-rows>'
            .'<table:table-rows><table:table-row>'.$cell('wide', 'table:number-columns-spanned="2"').'<table:covered-table-cell/>'.$cell('tall', 'table:number-rows-spanned="2"').'</table:table-row></table:table-rows>'
            .'<table:table-row-group><table:table-row table:number-rows-repeated="2">'.$cell('r', 'table:number-columns-repeated="2"').'<table:covered-table-cell/></table:table-row></table:table-row-group>'
            .'<table:table-row><table:table-cell><text:p>two</text:p><text:p/><text:p>lines</text:p><table:table><table:table-row>'.$cell('nested').'</table:table-row></table:table></table:table-cell><table:covered-table-cell table:number-columns-repeated="2"/><text:soft-page-break/></table:table-row>'
            .'<table:table-row table:number-rows-repeated="999999">'.$cell('many').'</table:table-row></table:table>'
            .'<table:table><table:table-row>'.$cell('plain').'</table:table-row><table:table-header-rows><table:table-row>'.$cell('late').'</table:table-row></table:table-header-rows></table:table>'
            .'<table:table><table:table-columns/></table:table>',
        ));

        $blocks = $imported->document->blocks;
        $content = $blocks[0]->data['content'];

        self::assertCount(2, $blocks);
        self::assertIsArray($content);
        self::assertTrue($blocks[0]->data['withHeadings']);
        self::assertSame([['Name', 'Value', 'Note'], ['wide', '', 'tall'], ['r', 'r', ''], ['r', 'r', ''], ['two<br>lines<br>nested', '', '']], \array_slice($content, 0, 5));
        self::assertCount(5 + 256, $content);
        self::assertSame(['many', '', ''], $content[5]);
        self::assertSame(['withHeadings' => false, 'stretched' => false, 'content' => [['plain'], ['late']]], $blocks[1]->data);
        self::assertSame([['merged-cell', '', 3], ['nested-table', '', 1]], $this->report($imported));
    }

    public function testSectionsTablesOfContentsAndTrackedChanges(): void
    {
        $imported = $this->import(Odt::text(
            '<text:sequence-decls/><text:tracked-changes><text:changed-region><text:deletion><text:p>old</text:p></text:deletion></text:changed-region>'
            .'<text:changed-region><text:insertion/></text:changed-region><text:changed-region><text:deletion/></text:changed-region></text:tracked-changes>'
            .'<text:table-of-content><text:index-body><text:index-title><text:p> Contents </text:p></text:index-title><text:p>Entry</text:p></text:index-body></text:table-of-content>'
            .'<text:table-of-content/><text:section><text:p>In a section</text:p></text:section>'
            .'<text:p><text:change-start/>new<text:change-end/><text:change/></text:p>',
        ));

        self::assertSame([
            ['toc', ['title' => 'Contents', 'depth' => 3, 'wide' => false]],
            ['toc', ['title' => '', 'depth' => 3, 'wide' => false]],
            ['paragraph', ['text' => 'In a section']],
            ['paragraph', ['text' => 'new']],
        ], $this->blocks($imported));
        self::assertSame([['tracked-change', 'deletion', 2]], $this->report($imported));
    }

    public function testHiddenTextDirectFormattingHeadersFootersAndMacrosAreReported(): void
    {
        $imported = $this->import(Odt::text(
            '<text:p>shown<text:span text:style-name="Hidden"> hidden</text:span><text:span text:style-name="HiddenChild"> also</text:span>'
            .'<text:span text:style-name="Red"> red</text:span><text:span text:style-name="Common"> common</text:span></text:p>',
        )->automatic(
            $this->text('Hidden', 'text:display="none"').$this->text('HiddenChild', '', 'Hidden')
            .$this->text('Red', 'fo:color="#ff0000" style:font-name="Serif" fo:font-size="14pt"'),
        )->styles(
            $this->text('Common', 'fo:color="#00ff00"'),
            '<office:master-styles><style:master-page><style:header><text:p>Running head</text:p></style:header><style:footer><text:p> </text:p></style:footer></style:master-page>'
            .'<style:master-page><style:footer><text:p>Page</text:p></style:footer></style:master-page></office:master-styles>',
        )->file('Basic/Standard/Module1.xml', 'macro')->file('Scripts/python/a.py', 'macro'));

        self::assertSame([['paragraph', ['text' => 'shown red common']]], $this->blocks($imported));
        self::assertSame([['header-footer', 'header', 1], ['header-footer', 'footer', 1], ['macro', '', 1], ['hidden-text', '', 2], ['formatting', 'colour', 1], ['formatting', 'font', 1], ['formatting', 'size', 1]], $this->report($imported));
    }

    public function testScriptsAloneAreMacros(): void
    {
        $imported = $this->import(Odt::text('<text:p>a</text:p>')->file('Scripts/python/a.py', 'macro'));

        self::assertSame([['macro', '', 1]], $this->report($imported));
    }

    public function testMetaIsTheMetadata(): void
    {
        $imported = $this->import(Odt::text('<text:p>a</text:p>')->meta(
            '<dc:title>The Title</dc:title><dc:subject>Subject</dc:subject><meta:initial-creator>Ann</meta:initial-creator><dc:creator>Bob</dc:creator>'
            .'<meta:keyword>a</meta:keyword><meta:keyword>b</meta:keyword><dc:description>About</dc:description><dc:language>fr</dc:language>'
            .'<meta:creation-date>2026-01-02T03:04:05</meta:creation-date><dc:date>2026-02-03T04:05:06</dc:date>',
        ));

        self::assertSame(['title' => 'The Title', 'subject' => 'Subject', 'creator' => 'Ann', 'keywords' => 'a, b', 'description' => 'About', 'language' => 'fr', 'created' => '2026-01-02T03:04:05', 'modified' => '2026-02-03T04:05:06'], $imported->metadata);
    }

    public function testAnArchiveWithoutTextIsRefused(): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('The archive has no text in "content.xml": it is not an OpenDocument text.');

        new DocumentImporter()->import(Zip::of(['mimetype' => 'application/vnd.oasis.opendocument.text', 'content.xml' => '<office:document-content '.Odt::NAMESPACES.'><office:body><office:spreadsheet/></office:body></office:document-content>']));
    }

    public function testStylesCombineWithTheirParents(): void
    {
        $imported = $this->import(Odt::text(
            '<text:p><text:span text:style-name="Child">both</text:span><text:span text:style-name="Two"> two</text:span>'
            .'<text:span text:style-name="SemiBold"> semi</text:span><text:span text:style-name="Lowered"> low</text:span></text:p>'
            .'<text:h text:outline-level="0">Zero</text:h>',
        )->automatic(
            $this->text('Parent', 'fo:font-weight="bold"').$this->text('Child', 'fo:font-style="italic"', 'Parent')
            .$this->text('Two', 'fo:font-weight="bold" fo:font-style="italic"').$this->text('SemiBold', 'fo:font-weight="600"')
            .$this->text('Lowered', 'style:text-position="-20% 58%"'),
        ));

        self::assertSame([
            ['paragraph', ['text' => '<b><i>both two</i> semi</b><sub> low</sub>']],
            ['header', ['text' => 'Zero', 'level' => 1]],
        ], $this->blocks($imported));
    }

    public function testListsReturnToOuterLevelsAndKeepParagraphsAfterNestedLists(): void
    {
        $imported = $this->import(Odt::text(
            '<text:list><text:list-item><text:p>One</text:p><text:list><text:list-item><text:p>One.a</text:p>'
            .'<text:list><text:list-item><text:p>Deep</text:p></text:list-item></text:list></text:list-item>'
            .'<text:list-item><text:p>One.b</text:p></text:list-item></text:list><text:p>One again</text:p></text:list-item></text:list>',
        ));

        $item = static fn (string $content, array $items = []): array => ['content' => $content, 'meta' => [], 'items' => $items];

        self::assertSame([['list', ['style' => 'unordered', 'meta' => [], 'items' => [
            $item('One', [$item('One.a', [$item('Deep')]), $item('One.b')]),
            $item('One again'),
        ]]]], $this->blocks($imported));
    }

    public function testLinkAndPictureEdgeCases(): void
    {
        $frame = static fn (string $href, string $alt = ''): string => '<draw:frame>'.$alt.'<draw:image xlink:href="'.$href.'"/></draw:frame>';
        $imported = $this->import(Odt::text(
            '<text:p><text:a xlink:href="https://outer.test/"><text:a xlink:href="https://inner.test/">in</text:a></text:a></text:p>'
            .'<text:p>'.$frame('Pictures/dot.png', '<svg:title> Titled </svg:title>').$frame('Pictures/dot.png', '<svg:desc> Described </svg:desc>')
            .$frame('HTTPS://images.test/A.png').$frame('Pictures/http://x.png').'</text:p>',
        )->file('Pictures/dot.png', Png::bytes()));

        self::assertSame(['<a href="https://inner.test/">in</a>', 'Titled', 'Described', ''], array_map(static fn (Block $block): mixed => $block->data['text'] ?? $block->data['alt'] ?? '', $imported->document->blocks));
        self::assertSame(['url' => 'HTTPS://images.test/A.png'], $imported->document->blocks[3]->data['file']);
        self::assertSame([['image', 'missing', 1]], $this->report($imported));
    }

    public function testNotesAndCellsEdgeCases(): void
    {
        $imported = $this->import(Odt::text(
            '<text:p>Text<text:note text:note-class="footnote"><text:note-body><text:list><text:list-header><text:p>header</text:p></text:list-header></text:list><text:soft-page-break/></text:note-body></text:note></text:p>'
            .'<table:table><table:table-row><table:table-cell><text:p text:style-name="Bold">bold</text:p><table:table><table:table-row><table:table-cell/><table:table-cell><text:p>x</text:p></table:table-cell><table:table-cell/></table:table-row></table:table></table:table-cell></table:table-row>'
            .'<table:table-row><table:table-cell><text:p>b</text:p></table:table-cell></table:table-row></table:table>',
        )->automatic('<style:style style:name="Bold" style:family="paragraph"><style:text-properties fo:font-weight="bold"/></style:style>'));

        self::assertSame([
            ['paragraph', ['text' => 'Text<sup data-footnote="fn-1"><a href="#footnote-fn-1">1</a></sup>']],
            ['table', ['withHeadings' => false, 'stretched' => false, 'content' => [['<b>bold</b><br>x'], ['b']]]],
            ['footnote', ['id' => 'fn-1', 'text' => 'header']],
        ], $this->blocks($imported));
    }

    public function testAnArchiveWithoutContentIsRefused(): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('The archive has no text in "content.xml"');

        new DocumentImporter()->import(Zip::of(['mimetype' => 'application/vnd.oasis.opendocument.text']));
    }

    private function import(Odt $odt): Imported
    {
        return new DocumentImporter()->import($odt->bytes());
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

    private function text(string $name, string $properties, string $parent = ''): string
    {
        return '<style:style style:name="'.$name.'" style:family="text"'.('' === $parent ? '' : ' style:parent-style-name="'.$parent.'"').'><style:text-properties '.$properties.'/></style:style>';
    }

    private function paragraph(string $name, string $properties): string
    {
        return '<style:style style:name="'.$name.'" style:family="paragraph"><style:paragraph-properties '.$properties.'/></style:style>';
    }
}
