<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit;

use FilesystemIterator;
use Phil\DocumentImport\DocumentExporter;
use Phil\DocumentImport\DocumentImporter;
use Phil\DocumentImport\Exception\ExportFailed;
use Phil\DocumentImport\Exported;
use Phil\DocumentImport\Format;
use Phil\DocumentImport\Imported;
use Phil\DocumentImport\Internal\Export\Blocks;
use Phil\DocumentImport\Internal\Export\DocxStylesheet;
use Phil\DocumentImport\Internal\Export\DocxWriter;
use Phil\DocumentImport\Internal\Export\ListItem;
use Phil\DocumentImport\Internal\Export\OdtStylesheet;
use Phil\DocumentImport\Internal\Export\OdtWriter;
use Phil\DocumentImport\Internal\Export\Picture;
use Phil\DocumentImport\Internal\Export\Run;
use Phil\DocumentImport\Internal\Export\Runs;
use Phil\DocumentImport\Internal\Export\XmlText;
use Phil\DocumentImport\Internal\Mark;
use Phil\DocumentImport\Media;
use Phil\DocumentImport\MediaMap;
use Phil\DocumentImport\Tests\Unit\Fixture\Png;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZipArchive;

#[CoversClass(DocumentExporter::class)]
#[CoversClass(Exported::class)]
#[CoversClass(MediaMap::class)]
#[CoversClass(Blocks::class)]
#[CoversClass(DocxWriter::class)]
#[CoversClass(DocxStylesheet::class)]
#[CoversClass(OdtWriter::class)]
#[CoversClass(OdtStylesheet::class)]
#[CoversClass(ListItem::class)]
#[CoversClass(Picture::class)]
#[CoversClass(Run::class)]
#[CoversClass(Runs::class)]
#[CoversClass(XmlText::class)]
final class ExportTest extends TestCase
{
    private const string SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><script>alert(1)</script><rect width="1" height="1"/></svg>';

    /**
     * @return list<array{string, array<string, mixed>, array<string, mixed>}>
     */
    private function blocks(): array
    {
        return [
            ['header', ['text' => 'Title <i>one</i>', 'level' => 1], []],
            ['paragraph', ['text' => 'Hello <b>bold</b>, <strong>strong</strong> <em>em</em> <u>u</u> <s>s</s> <mark>m</mark> <code>c</code> x<sub>2</sub> <a href="https://example.com/?a=1&amp;b=2">link</a> <a href="javascript:x">bad</a> &amp; note<sup data-footnote="fn-1"><a href="#footnote-fn-1">1</a></sup>.<br>Line   two'], ['alignment' => ['alignment' => 'center']]],
            ['paragraph', ['text' => 'Right'], ['alignment' => ['alignment' => 'right']]],
            ['paragraph', ['text' => 'Justified'], ['alignment' => ['alignment' => 'justify']]],
            ['list', ['style' => 'ordered', 'meta' => ['start' => 3, 'counterType' => 'lower-roman'], 'items' => [['content' => 'One', 'meta' => [], 'items' => [['content' => 'Nested', 'meta' => [], 'items' => []]]], ['content' => 'Two', 'meta' => [], 'items' => []]]], []],
            ['list', ['style' => 'unordered', 'meta' => [], 'items' => [['content' => 'Bullet', 'meta' => [], 'items' => []]]], []],
            ['list', ['style' => 'checklist', 'meta' => [], 'items' => [['content' => 'Done', 'meta' => ['checked' => true], 'items' => []], ['content' => 'Todo', 'meta' => ['checked' => false], 'items' => []]]], []],
            ['quote', ['text' => 'A quote', 'caption' => 'Someone', 'alignment' => 'left'], []],
            ['quote', ['text' => 'Centred', 'caption' => '', 'alignment' => 'center'], []],
            ['code', ['code' => "echo 1;\r\n  echo 2;"], []],
            ['table', ['withHeadings' => true, 'content' => [['A', 'B'], ['1', ['text' => '<b>2</b>']], ['3']]], []],
            ['table', ['withHeadings' => false, 'content' => []], []],
            ['image', ['file' => ['url' => 'media/dot.png'], 'alt' => 'A dot', 'caption' => 'Cap'], []],
            ['image', ['file' => ['url' => 'media/vector.svg'], 'caption' => 'Vector'], []],
            ['image', ['file' => ['url' => 'https://example.com/remote.png'], 'alt' => 'Remote'], []],
            ['image', ['file' => ['url' => 'media/notes.txt']], []],
            ['delimiter', [], []],
            ['pageBreak', [], []],
            ['toc', ['title' => 'Contents'], []],
            ['warning', ['title' => 'Careful', 'message' => 'Hot'], []],
            ['linkTool', ['link' => 'https://example.com/page', 'meta' => ['title' => 'A page']], []],
            ['attaches', ['file' => ['url' => 'https://example.com/a.pdf'], 'title' => ''], []],
            ['embed', ['source' => 'ftp://example.com/v', 'caption' => 'Video'], []],
            ['raw', ['html' => '<p>x</p>'], []],
            ['footnote', ['id' => 'fn-1', 'text' => 'The <i>note</i> <a href="https://example.com/n">here</a>.'], []],
            ['footnote', ['id' => 'fn-2', 'text' => 'Never referenced.'], []],
        ];
    }

    private function document(): Document
    {
        return new Document(array_map(static fn (array $block, int $index): Block => new Block('b'.$index, $block[0], $block[1], $block[2]), $this->blocks(), array_keys($this->blocks())));
    }

    private function exporter(): DocumentExporter
    {
        return new DocumentExporter(new MediaMap([
            'media/dot.png' => new Media('dot.png', Png::bytes()),
            'media/vector.svg' => self::SVG,
            'media/notes.txt' => 'plain text',
        ]));
    }

    /**
     * @return iterable<string, array{Format, bool}>
     */
    public static function archives(): iterable
    {
        yield 'docx' => [Format::Docx, true];
        yield 'odt' => [Format::Odt, true];
        yield 'empty docx' => [Format::Docx, false];
        yield 'empty odt' => [Format::Odt, false];
    }

    /**
     * The edges of each block: clamped levels and starts, deep lists, captions of spaces, footnotes referenced twice
     * or from a table, metadata of spaces or of damaged dates, images of several sizes and types.
     */
    #[DataProvider('edgeArchives')]
    public function testTheEdgesOfEachBlockAreWritten(Format $format): void
    {
        $deep = ['content' => 'Level 9', 'meta' => [], 'items' => [['content' => 'Level 10', 'meta' => [], 'items' => []]]];

        for ($level = 8; $level >= 1; --$level) {
            $deep = ['content' => 'Level '.$level, 'meta' => [], 'items' => [$deep]];
        }

        $wide = "\x89PNG\r\n\x1A\n\0\0\0\rIHDR".pack('N', 1000).pack('N', 333).str_repeat("\0", 8);
        $tall = "\x89PNG\r\n\x1A\n\0\0\0\rIHDR".pack('N', 7).pack('N', 20_001).str_repeat("\0", 8);
        $blocks = [
            ['header', ['text' => 'Zero', 'level' => 0], []],
            ['header', ['text' => 'Seven', 'level' => 7], []],
            ['header', ['text' => 'No level'], []],
            ['paragraph', ['text' => 'Centred'], ['alignment' => ['alignment' => 'center']]],
            ['pageBreak', [], []],
            ['paragraph', ['text' => 'Centred after a break'], ['alignment' => ['alignment' => 'center']]],
            ['paragraph', ['text' => 'Left'], ['alignment' => ['alignment' => 'left']]],
            ['paragraph', ['text' => 'Damaged tune'], ['alignment' => 'center']],
            ['paragraph', ['text' => 'Alignment of a number'], ['alignment' => ['alignment' => 5]]],
            ['paragraph', ['text' => 'One<sup data-footnote="fn-a">1</sup> again<sup data-footnote="fn-a">1</sup> missing<sup data-footnote="fn-z"> 9 </sup> <span data-footnote="fn-span">span</span> <a href=" https://example.com/trimmed ">trimmed</a> <a href="HTTPS://EXAMPLE.COM/UP">up</a> <a href="see https://example.com">not</a> <a href="tel:+15550100">call</a>'], []],
            ['list', ['style' => 'ordered', 'meta' => ['start' => 0, 'counterType' => 'upper-greek'], 'items' => [['content' => 'From zero', 'meta' => [], 'items' => []]]], []],
            ['list', ['style' => 'ordered', 'meta' => ['start' => -5, 'counterType' => 'upper-alpha'], 'items' => [['content' => 'From minus five', 'meta' => [], 'items' => []]]], []],
            ['list', ['style' => 'unordered', 'meta' => ['start' => 4], 'items' => [$deep]], []],
            ['list', ['style' => 'checklist', 'meta' => [], 'items' => [['content' => 'Done <b>bold</b> end', 'meta' => [], 'items' => []]]], []],
            ['list', ['style' => 'ordered', 'items' => 'damaged'], []],
            ['quote', ['text' => 'Two <i>runs</i> here', 'caption' => 'By <b>someone</b> known'], []],
            ['quote', ['text' => 'No caption', 'caption' => '   '], []],
            ['code', ['code' => "a\tb\n  c    d\n e"], []],
            ['table', ['content' => [['<sup data-footnote="fn-b">2</sup> first', 'second'], ['third', ['text' => 'fourth']], 'damaged']], []],
            ['image', ['file' => ['url' => 'media/wide.png'], 'caption' => '   '], []],
            ['image', ['file' => ['url' => 'media/tall.png'], 'caption' => 'Tall'], []],
            ['image', ['file' => ['url' => 'media/thin.png']], []],
            ['image', ['file' => ['url' => 'media/again.png']], []],
            ['image', ['file' => ['url' => 'media/anim.gif']], []],
            ['image', ['file' => ['url' => 'HTTPS://EXAMPLE.COM/REMOTE.PNG']], []],
            ['image', ['file' => ['url' => 'media https://example.com/x.png']], []],
            ['toc', ['title' => '   '], []],
            ['warning', ['title' => 'Mind <i>this</i>', 'message' => ''], []],
            ['linkTool', ['link' => 'HTTPS://EXAMPLE.COM/UP', 'meta' => ['title' => '   ']], []],
            ['linkTool', ['link' => 'https://example.com/t', 'meta' => ['title' => ' <b>Title</b> ']], []],
            ['linkTool', ['link' => 'see https://example.com', 'meta' => ['title' => 'Not a link']], []],
            ['footnote', ['id' => 'fn-a', 'text' => 'First note.'], []],
            ['footnote', ['id' => 'fn-b', 'text' => 'Second note.'], []],
        ];
        $document = new Document(array_map(static fn (array $block, int $index): Block => new Block('e'.$index, $block[0], $block[1], $block[2]), $blocks, array_keys($blocks)));
        $exporter = new DocumentExporter(new MediaMap([
            'media/wide.png' => new Media('wide.png', $wide),
            'media/tall.png' => new Media('tall.png', $tall),
            'media/thin.png' => new Media('thin.png', "\x89PNG\r\n\x1A\n\0\0\0\rIHDR".pack('N', 1000).pack('N', 2).str_repeat("\0", 8)),
            'media/again.png' => new Media('again.png', Png::bytes()),
            'media/anim.gif' => new Media('anim.gif', "GIF89a\x20\x03\x58\x02"),
        ]));
        $metadata = ['title' => '   ', 'subject' => ' Kept ', 'created' => 'x2026-01-02', 'modified' => '2026-01-02x', 'language' => 'en'];
        $this->assertSnapshot($this->contents($exporter->export($document, $format, $metadata)->bytes), __DIR__.'/Fixture/export/edge-'.$format->value);

        $dated = $this->contents($exporter->export(new Document(), $format, ['created' => "2026-01-02\n", 'modified' => '2026-01-02T03:04+05:00'])->bytes);
        $this->assertSnapshot($dated, __DIR__.'/Fixture/export/dated-'.$format->value);
    }

    /**
     * @return iterable<string, array{Format}>
     */
    public static function edgeArchives(): iterable
    {
        yield 'docx' => [Format::Docx];
        yield 'odt' => [Format::Odt];
    }

    /**
     * Every entry of the archive, byte for byte, as the files under `Fixture/export/` hold them: the markup each
     * block, run, style and metadata writes. `PHIL_UPDATE_EXPORTS=1` writes them again after an intended change.
     */
    #[DataProvider('archives')]
    public function testTheArchiveIsWrittenEntryByEntry(Format $format, bool $rich): void
    {
        $document = $rich ? new Document([...$this->document()->blocks, ...array_map(static fn (int $level): Block => new Block('h'.$level, 'header', ['text' => 'Level '.$level, 'level' => $level]), range(2, 6))]) : new Document();
        $metadata = $rich ? ['title' => 'A <title>', 'subject' => 'Subject', 'creator' => 'Author & co', 'keywords' => 'one, two', 'description' => 'About', 'language' => 'fr-CA', 'created' => '2026-01-02T03:04:05Z', 'modified' => '2026-02-03T04:05:06Z', 'unknown' => 'ignored'] : [];
        $entries = $this->contents($this->exporter()->export($document, $format, $metadata)->bytes);
        $this->assertSnapshot($entries, __DIR__.'/Fixture/export/'.($rich ? '' : 'empty-').$format->value);
    }

    public function testADocxExportImportsBackAsTheSameBlocks(): void
    {
        $exported = $this->exporter()->export($this->document(), Format::Docx, ['title' => 'T', 'creator' => 'Ann', 'created' => '2026-11-12T10:00:00Z', 'modified' => 'yesterday', 'subject' => ' ']);
        $imported = new DocumentImporter()->import($exported->bytes, Format::Docx);

        self::assertSame('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $exported->mediaType());
        self::assertSame('docx', $exported->extension());
        self::assertSame([
            ['header', ['text' => 'Title <i>one</i>', 'level' => 1]],
            ['paragraph', ['text' => 'Hello <b>bold</b>, <b>strong</b> <i>em</i> <u class="cdx-underline">u</u> <s class="cdx-strikethrough">s</s> <mark class="cdx-marker">m</mark> <code class="inline-code">c</code> x<sub>2</sub> <a href="https://example.com/?a=1&amp;b=2">link</a> bad &amp; note<sup data-footnote="fn-1"><a href="#footnote-fn-1">1</a></sup>.<br>Line two']],
            ['paragraph', ['text' => 'Right']],
            ['paragraph', ['text' => 'Justified']],
            ['list', ['style' => 'ordered', 'meta' => ['start' => 3, 'counterType' => 'lower-roman'], 'items' => [$this->item('One', [$this->item('Nested')]), $this->item('Two')]]],
            ['list', ['style' => 'unordered', 'meta' => [], 'items' => [$this->item('Bullet')]]],
            ['list', ['style' => 'unordered', 'meta' => [], 'items' => [$this->item('☑ Done'), $this->item('☐ Todo')]]],
            ['quote', ['text' => 'A quote<br>— Someone', 'caption' => '', 'alignment' => 'left']],
            ['quote', ['text' => 'Centred', 'caption' => '', 'alignment' => 'left']],
            ['code', ['code' => "echo 1;\n  echo 2;"]],
            ['table', ['withHeadings' => true, 'stretched' => false, 'content' => [['A', 'B'], ['1', '<b>2</b>'], ['3', '']]]],
            ['image', ['file' => ['url' => 'media/image1.png'], 'caption' => '', 'alt' => 'A dot', 'withBorder' => false, 'withBackground' => false, 'stretched' => false]],
            ['paragraph', ['text' => 'Cap']],
            ['paragraph', ['text' => 'Vector']],
            ['paragraph', ['text' => '<a href="https://example.com/remote.png">Remote</a>']],
            ['paragraph', ['text' => '* * *']],
            ['pageBreak', []],
            ['toc', ['title' => 'Contents', 'depth' => 3, 'wide' => false]],
            ['quote', ['text' => '<b>Careful</b>', 'caption' => '', 'alignment' => 'left']],
            ['quote', ['text' => 'Hot', 'caption' => '', 'alignment' => 'left']],
            ['paragraph', ['text' => '<a href="https://example.com/page">A page</a>']],
            ['paragraph', ['text' => '<a href="https://example.com/a.pdf">https://example.com/a.pdf</a>']],
            ['paragraph', ['text' => 'Never referenced.']],
            ['footnote', ['id' => 'fn-1', 'text' => 'The <i>note</i> here (https://example.com/n).']],
        ], $this->read($imported));
        self::assertSame(['center', 'right', 'justify'], array_map($this->alignment(...), \array_slice($imported->document->blocks, 1, 3)));
        self::assertSame(['title' => 'T', 'creator' => 'Ann', 'created' => '2026-11-12T10:00:00Z'], $imported->metadata);
        self::assertSame([['image', 'image/svg+xml', 1], ['image', 'not fetched', 1], ['image', 'application/octet-stream', 1], ['block', 'link', 1], ['block', 'raw', 1]], $this->report($exported));
    }

    public function testAnOdtExportImportsBackAsTheSameBlocks(): void
    {
        $exported = $this->exporter()->export($this->document(), Format::Odt, ['title' => 'T', 'language' => 'fr', 'keywords' => 'a, b', 'description' => 'D', 'subject' => 'S', 'created' => '2026-11-12T10:00:00', 'modified' => '2026-11-13T10:00:00']);
        $imported = new DocumentImporter()->import($exported->bytes, Format::Odt);
        $blocks = $this->read($imported);

        self::assertSame('application/vnd.oasis.opendocument.text', $exported->mediaType());
        self::assertSame('odt', $exported->extension());
        self::assertSame(['header', ['text' => 'Title <i>one</i>', 'level' => 1]], $blocks[0]);
        self::assertSame('Hello <b>bold</b>, <b>strong</b> <i>em</i> <u class="cdx-underline">u</u> <s class="cdx-strikethrough">s</s> <mark class="cdx-marker">m</mark> <code class="inline-code">c</code> x<sub>2</sub> <a href="https://example.com/?a=1&amp;b=2">link</a> bad &amp; note<sup data-footnote="fn-1"><a href="#footnote-fn-1">1</a></sup>.<br>Line two', $blocks[1][1]['text']);
        self::assertSame(['list', ['style' => 'ordered', 'meta' => ['start' => 3, 'counterType' => 'lower-roman'], 'items' => [$this->item('One', [$this->item('Nested')]), $this->item('Two')]]], $blocks[4]);
        self::assertSame(['table', ['withHeadings' => true, 'stretched' => false, 'content' => [['A', 'B'], ['1', '<b>2</b>'], ['3', '']]]], $blocks[10]);
        self::assertSame(['image', ['file' => ['url' => 'media/image1.png'], 'caption' => '', 'alt' => 'A dot', 'withBorder' => false, 'withBackground' => false, 'stretched' => false]], $blocks[11]);
        self::assertSame(['image', ['file' => ['url' => 'media/image2.svg'], 'caption' => '', 'alt' => 'Vector', 'withBorder' => false, 'withBackground' => false, 'stretched' => false]], $blocks[13]);
        self::assertStringNotContainsString('script', $imported->media['media/image2.svg']->contents);
        self::assertContains(['toc', ['title' => 'Contents', 'depth' => 3, 'wide' => false]], $blocks);
        self::assertContains(['pageBreak', []], $blocks);
        self::assertSame(['footnote', ['id' => 'fn-1', 'text' => 'The <i>note</i> <a href="https://example.com/n">here</a>.']], $blocks[\count($blocks) - 1]);
        self::assertSame(['center', 'right', 'justify'], array_map($this->alignment(...), \array_slice($imported->document->blocks, 1, 3)));
        self::assertSame(['title' => 'T', 'subject' => 'S', 'keywords' => 'a, b', 'description' => 'D', 'language' => 'fr', 'created' => '2026-11-12T10:00:00', 'modified' => '2026-11-13T10:00:00'], $imported->metadata);
        self::assertSame([['image', 'not fetched', 1], ['image', 'application/octet-stream', 1], ['block', 'link', 1], ['block', 'raw', 1]], $this->report($exported));
        self::assertSame(['mimetype', 'META-INF/manifest.xml', 'content.xml', 'styles.xml', 'meta.xml', 'Pictures/image1.png', 'Pictures/image2.svg'], $this->entries($exported->bytes));
    }

    public function testTheSameDocumentGivesTheSameBytes(): void
    {
        self::assertSame($this->exporter()->export($this->document(), Format::Docx)->bytes, $this->exporter()->export($this->document(), Format::Docx)->bytes);
        self::assertSame($this->exporter()->export($this->document(), Format::Odt)->bytes, $this->exporter()->export($this->document(), Format::Odt)->bytes);
    }

    public function testTheMimetypeOfAnOdtIsFirstAndStored(): void
    {
        $bytes = new DocumentExporter()->export(new Document(), Format::Odt)->bytes;

        self::assertSame('mimetypeapplication/vnd.oasis.opendocument.text', substr($bytes, 30, 47));
    }

    public function testWithoutAMediaSourceImagesAreReportedAndLinked(): void
    {
        $exported = new DocumentExporter()->export(new Document([new Block('i', 'image', ['file' => ['url' => 'https://example.com/a.png']]), new Block('j', 'image', ['file' => ['url' => '']])]), Format::Docx);

        self::assertSame([['image', 'not fetched', 2]], $this->report($exported));
        self::assertSame([['paragraph', ['text' => '<a href="https://example.com/a.png">https://example.com/a.png</a>']]], $this->read(new DocumentImporter()->import($exported->bytes, Format::Docx)));
    }

    public function testARefusedSvgIsNotWritten(): void
    {
        $exported = new DocumentExporter(new MediaMap(['v.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><iframe/></foreignObject>']))->export(new Document([new Block('i', 'image', ['file' => ['url' => 'v.svg']])]), Format::Odt);

        self::assertSame(1, $exported->report->count());
    }

    /**
     * @return iterable<string, array{Format}>
     */
    public static function notWordProcessing(): iterable
    {
        yield 'markdown' => [Format::Markdown];
        yield 'pdf' => [Format::Pdf];
        yield 'flat' => [Format::Fodt];
    }

    #[DataProvider('notWordProcessing')]
    public function testOnlyDocxAndOdtAreExported(Format $format): void
    {
        $this->expectExceptionObject(ExportFailed::because(\sprintf('A document is exported as DOCX or ODT; "%s" given.', $format->value)));
        new DocumentExporter()->export(new Document(), $format);
    }

    public function testAnImportedDocumentExportsWithItsImages(): void
    {
        $docx = $this->exporter()->export($this->document(), Format::Docx);
        $imported = new DocumentImporter()->import($docx->bytes, Format::Docx);
        $again = new DocumentExporter(new MediaMap($imported->media))->export($imported->document, Format::Odt);

        self::assertSame(['mimetype', 'META-INF/manifest.xml', 'content.xml', 'styles.xml', 'meta.xml', 'Pictures/image1.png'], $this->entries($again->bytes));
        self::assertNull(new MediaMap()->fetch('x'));
    }

    public function testInlineHtmlBecomesRuns(): void
    {
        $runs = Runs::of('a <b>b <i>c</i><b>d</b></b><br><span>e</span><a href="mailto:x@example.com">f</a><a href="tel:1">g</a><a href="/local">h</a><sup data-footnote="">2</sup>');

        self::assertEquals([
            new Run('a '),
            new Run('b ', [Mark::Bold]),
            new Run('c', [Mark::Bold, Mark::Italic]),
            new Run('d', [Mark::Bold]),
            new Run('', [], null, true),
            new Run('e'),
            new Run('f', [], 'mailto:x@example.com'),
            new Run('g', [], 'tel:1'),
            new Run('h'),
            new Run('2', [Mark::Superscript]),
        ], $runs);
        self::assertSame([], Runs::of('  '));
        self::assertSame('ab', Runs::text('<i>a</i>b'));
        self::assertTrue(new Run('x', [Mark::Code])->has(Mark::Code));
        self::assertFalse(new Run('x')->has(Mark::Code));
    }

    public function testPicturesKnowTheirSize(): void
    {
        $jpeg = "\xFF\xD8\xFF\xE0\x00\x04ab\xFF\xC4\x00\x02\xFF\xC0\x00\x11\x08\x01\x2C\x02\x58";
        $gif = "GIF89a\x20\x03\x58\x02";

        self::assertSame([1, 1, 'png'], $this->measure(new Picture(Png::bytes(), 'image/png', '')));
        self::assertSame([600, 300, 'jpeg'], $this->measure(new Picture($jpeg, 'image/jpeg', '')));
        self::assertSame([800, 600, 'gif'], $this->measure(new Picture($gif, 'image/gif', '')));
        self::assertSame([400, 300, 'jpeg'], $this->measure(new Picture("\xFF\xD8\xFF", 'image/jpeg', '')));
        self::assertSame([400, 300, 'svg'], $this->measure(new Picture('<svg/>', 'image/svg+xml', '')));
        self::assertSame([1, 20_000, 'png'], $this->measure(new Picture("\x89PNG\r\n\x1A\n\0\0\0\rIHDR".pack('N', 0).pack('N', 20_000), 'image/png', '')));
        // A segment of a declared size below two is skipped as two bytes; markers C4, C8 and CC are no frames, CF is.
        self::assertSame([600, 300, 'jpeg'], $this->measure(new Picture("\xFF\xD8\xFF\xE0\x00\x01\xFF\xC8\x00\x02\xFF\xCC\x00\x02\xFF\xCF\x00\x11\x08\x01\x2C\x02\x58", 'image/jpeg', '')));
        // A frame whose last byte is missing gives no size.
        self::assertSame([400, 300, 'jpeg'], $this->measure(new Picture("\xFF\xD8\xFF\xC0\x00\x11\x08\x01\x2C\x02", 'image/jpeg', '')));
        self::assertSame([600, 300, 'jpeg'], $this->measure(new Picture("\xFF\xD8\xFF\xC0\x00\x11\x08\x01\x2C\x02\x58", 'image/jpeg', '')));
        self::assertSame([400, 300, 'png'], $this->measure(new Picture('short', 'image/png', '')));
        self::assertSame([400, 300, 'gif'], $this->measure(new Picture('GIF8', 'image/gif', '')));
        self::assertSame([20_000, 1, 'png'], $this->measure(new Picture("\x89PNG\r\n\x1A\n\0\0\0\rIHDR\x00\x01\x00\x00\0\0\0\0", 'image/png', '')));
        self::assertSame([6.0, 3.0], new Picture(str_repeat("\0", 16)."\0\0\x04\xB0\0\0\x02\x58", 'image/png', '')->inches());
        self::assertSame([1 / 96, 1 / 96], new Picture(Png::bytes(), 'image/png', '')->inches());
        self::assertSame('a &amp; &lt;b&gt; &quot;c&quot; &apos;d&apos;', XmlText::escape("a & <b> \"c\" 'd'\x01\u{FFFE}"));
    }

    /**
     * @return array{int, int, string}
     */
    private function measure(Picture $picture): array
    {
        return [$picture->width, $picture->height, $picture->extension()];
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return array{content: string, meta: array{}, items: list<array<string, mixed>>}
     */
    private function item(string $content, array $items = []): array
    {
        return ['content' => $content, 'meta' => [], 'items' => $items];
    }

    private function alignment(Block $block): ?string
    {
        $tune = $block->tune('alignment');

        return \is_array($tune) && \is_string($tune['alignment'] ?? null) ? $tune['alignment'] : null;
    }

    /**
     * @return list<array{string, array<array-key, mixed>}>
     */
    private function read(Imported $imported): array
    {
        return array_map(static function (Block $block): array {
            $array = $block->toArray();

            return [$array['type'], $array['data']];
        }, $imported->document->blocks);
    }

    /**
     * @return list<array{string, string, int}>
     */
    private function report(Exported $exported): array
    {
        return array_map(static fn (array $row): array => [$row['what'], $row['detail'], $row['count']], $exported->report->toArray());
    }

    /**
     * @return list<string>
     */
    private function entries(string $bytes): array
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'export');
        file_put_contents($path, $bytes);
        $zip = new ZipArchive();
        $zip->open($path);

        $names = [];

        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $names[] = (string) $zip->getNameIndex($index);
        }

        $zip->close();
        unlink($path);

        return $names;
    }

    /**
     * @return array<string, string>
     */
    private function contents(string $bytes): array
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'phil-entries');
        file_put_contents($path, $bytes);
        $zip = new ZipArchive();
        self::assertTrue($zip->open($path));
        $entries = [];

        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $name = (string) $zip->getNameIndex($index);
            $entries[$name] = (string) $zip->getFromIndex($index);
        }

        $zip->close();
        unlink($path);

        return $entries;
    }

    /**
     * @param array<string, string> $entries
     */
    private function assertSnapshot(array $entries, string $directory): void
    {
        if ('1' === getenv('PHIL_UPDATE_EXPORTS')) {
            foreach ($entries as $name => $bytes) {
                if (!is_dir(\dirname($directory.'/'.$name))) {
                    mkdir(\dirname($directory.'/'.$name), recursive: true);
                }

                file_put_contents($directory.'/'.$name, $bytes);
            }
        }

        foreach ($entries as $name => $bytes) {
            self::assertFileExists($directory.'/'.$name);
            self::assertSame(file_get_contents($directory.'/'.$name), $bytes, $name);
        }

        $expected = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            self::assertInstanceOf(SplFileInfo::class, $file);
            $expected[] = substr($file->getPathname(), \strlen($directory) + 1);
        }

        sort($expected);
        $names = array_keys($entries);
        sort($names);
        self::assertSame($expected, $names);
    }
}
