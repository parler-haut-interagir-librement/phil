<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit;

use Phil\DocumentImport\DocumentImporter;
use Phil\DocumentImport\Exception\InvalidDocument;
use Phil\DocumentImport\Exception\LimitExceeded;
use Phil\DocumentImport\Exception\UnsupportedFormat;
use Phil\DocumentImport\Format;
use Phil\DocumentImport\Imported;
use Phil\DocumentImport\Internal\Archive;
use Phil\DocumentImport\Internal\Builder;
use Phil\DocumentImport\Internal\Docx\DocxReader;
use Phil\DocumentImport\Internal\Docx\DocxStyles;
use Phil\DocumentImport\Internal\Odt\OdtReader;
use Phil\DocumentImport\Internal\Odt\OdtStyles;
use Phil\DocumentImport\Internal\Role;
use Phil\DocumentImport\Limits;
use Phil\DocumentImport\Media;
use Phil\DocumentImport\MediaFolder;
use Phil\DocumentImport\MediaStore;
use Phil\DocumentImport\StyleMap;
use Phil\DocumentImport\StyleRole;
use Phil\DocumentImport\Tests\Unit\Fixture\Docx;
use Phil\DocumentImport\Tests\Unit\Fixture\Odt;
use Phil\DocumentImport\Tests\Unit\Fixture\Png;
use Phil\DocumentImport\Tests\Unit\Fixture\Zip;
use Phil\SvgSanitizer\SvgSanitizer;
use Phil\Ujo\Block;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZipArchive;

#[CoversClass(DocumentImporter::class)]
#[CoversClass(Format::class)]
#[CoversClass(Archive::class)]
#[CoversClass(Builder::class)]
#[CoversClass(DocxReader::class)]
#[CoversClass(DocxStyles::class)]
#[CoversClass(OdtReader::class)]
#[CoversClass(OdtStyles::class)]
#[CoversClass(Role::class)]
#[CoversClass(StyleMap::class)]
#[CoversClass(StyleRole::class)]
#[CoversClass(Media::class)]
final class FormatsTest extends TestCase
{
    private const string FLAT = '<?xml version="1.0"?><office:document '.Odt::NAMESPACES.' office:mimetype="application/vnd.oasis.opendocument.text" office:version="1.3">'
        .'<office:meta><dc:title>Flat</dc:title></office:meta>'
        .'<office:styles><style:style style:name="Quotations" style:family="paragraph"/><style:style style:name="Chapter" style:display-name="Chapter Title" style:family="paragraph"/></office:styles>'
        .'<office:automatic-styles><style:style style:name="B" style:family="text"><style:text-properties fo:font-weight="bold"/></style:style></office:automatic-styles>'
        .'<office:master-styles><style:master-page><style:header><text:p>Head</text:p></style:header></style:master-page></office:master-styles>'
        .'%s<office:body><office:text><text:h text:outline-level="1">Title</text:h><text:p text:style-name="Quotations">Quoted</text:p>'
        .'<text:p text:style-name="Chapter">Chapter</text:p><text:p>Some <text:span text:style-name="B">bold</text:span><draw:frame><draw:image><office:binary-data>'.Png::BASE64.'</office:binary-data></draw:image><svg:desc>Dot</svg:desc></draw:frame></text:p>'
        .'</office:text></office:body></office:document>';

    public function testAFlatOpenDocumentTextIsReadLikeAPackagedOne(): void
    {
        $imported = new DocumentImporter(styles: new StyleMap(['chapter title' => StyleRole::Heading2]))->import(\sprintf(self::FLAT, '<office:scripts><office:script/></office:scripts>'), filename: 'notes.fodt');

        self::assertSame(Format::Fodt, $imported->format);
        self::assertSame([
            ['header', ['text' => 'Title', 'level' => 1]],
            ['quote', ['text' => 'Quoted', 'caption' => '', 'alignment' => 'left']],
            ['header', ['text' => 'Chapter', 'level' => 2]],
            ['paragraph', ['text' => 'Some <b>bold</b>']],
            ['image', ['file' => ['url' => 'media/image.png'], 'caption' => '', 'alt' => 'Dot', 'withBorder' => false, 'withBackground' => false, 'stretched' => false]],
        ], $this->blocks($imported));
        self::assertSame(['title' => 'Flat'], $imported->metadata);
        self::assertSame([['header-footer', 'header', 1], ['macro', '', 1]], $this->report($imported));
        self::assertSame(Format::Fodt, Format::detect(\sprintf(self::FLAT, '')));
    }

    public function testAFlatDocumentWithoutScriptsReportsNoMacro(): void
    {
        self::assertSame([['header-footer', 'header', 1]], $this->report(new DocumentImporter()->import(\sprintf(self::FLAT, '<office:scripts/>'), Format::Fodt)));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badFlatDocuments(): iterable
    {
        yield 'spreadsheet' => [str_replace('opendocument.text', 'opendocument.spreadsheet', \sprintf(self::FLAT, '')), 'The file is not a flat OpenDocument text: its root is not an office:document of a text.'];
        yield 'content root' => ['<office:document-content '.Odt::NAMESPACES.' office:mimetype="application/vnd.oasis.opendocument.text"/>', 'The file is not a flat OpenDocument text: its root is not an office:document of a text.'];
        yield 'other namespace' => ['<office:document xmlns:office="urn:other" office:mimetype="application/vnd.oasis.opendocument.text"/>', 'The file is not a flat OpenDocument text: its root is not an office:document of a text.'];
        yield 'no body' => ['<office:document '.Odt::NAMESPACES.' office:mimetype="application/vnd.oasis.opendocument.text"/>', 'The flat document has no office:text in its body.'];
        yield 'not XML' => ['<office:document', 'The entry "content.xml" is not well-formed XML.'];
        yield 'empty' => [" \n", 'The entry "content.xml" is not well-formed XML.'];
        yield 'bad encoding' => ['<?xml version="1.0" encoding="UTF-8"?><office:document>'."\xDD\x63".'</office:document>', 'The entry "content.xml" is not well-formed XML.'];
        yield 'document type after a comment' => ['<?xml version="1.0"?><!-- c --><!DOCTYPE x><x/>', 'The entry "content.xml" declares a document type, which no office document does.'];
        yield 'document type' => ['<!DOCTYPE x [<!ENTITY a "b">]><office:document '.Odt::NAMESPACES.' office:mimetype="application/vnd.oasis.opendocument.text"/>', 'The entry "content.xml" declares a document type, which no office document does.'];
    }

    #[DataProvider('badFlatDocuments')]
    public function testABadFlatDocumentIsRefused(string $xml, string $message): void
    {
        $this->expectExceptionObject(InvalidDocument::because($message));
        new DocumentImporter()->import($xml, Format::Fodt);
    }

    public function testAFlatDocumentLargerThanAnEntryIsRefused(): void
    {
        $this->expectExceptionObject(LimitExceeded::because('The file is larger than 100 bytes.'));
        new DocumentImporter(limits: new Limits(entryBytes: 100))->import(\sprintf(self::FLAT, ''), Format::Fodt);
    }

    public function testStrictOfficeOpenXmlIsReadLikeTransitional(): void
    {
        $strict = [
            'http://schemas.openxmlformats.org/wordprocessingml/2006/main' => 'http://purl.oclc.org/ooxml/wordprocessingml/main',
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships' => 'http://purl.oclc.org/ooxml/officeDocument/relationships',
            'http://schemas.openxmlformats.org/drawingml/2006/main' => 'http://purl.oclc.org/ooxml/drawingml/main',
            'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing' => 'http://purl.oclc.org/ooxml/drawingml/wordprocessingDrawing',
        ];
        $docx = Docx::body('<w:p><w:pPr><w:pStyle w:val="H"/><w:jc w:val="start"/></w:pPr><w:r><w:t>Strict</w:t></w:r></w:p>'
            .'<w:p><w:r><w:rPr><w:b w:val="true"/></w:rPr><w:t>bold</w:t></w:r><w:r><w:drawing><wp:inline><wp:docPr id="1" name="P" descr="Dot"/><a:graphic><a:graphicData><a:blip r:embed="rIdImg"/></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>')
            ->styles('<w:style w:type="paragraph" w:styleId="H"><w:name w:val="heading 2"/></w:style>')
            ->part('image', 'media/image1.png', Png::bytes(), 'rIdImg')
            ->bytes();
        $imported = new DocumentImporter()->import($this->rewrite($docx, $strict), filename: 'strict.docx');

        self::assertSame([
            ['header', ['text' => 'Strict', 'level' => 2]],
            ['paragraph', ['text' => '<b>bold</b>']],
            ['image', ['file' => ['url' => 'media/image1.png'], 'caption' => '', 'alt' => 'Dot', 'withBorder' => false, 'withBackground' => false, 'stretched' => false]],
        ], $this->blocks($imported));
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function legacy(): iterable
    {
        yield 'compound file named .docx' => ["\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1rest", 'report.docx'];
        yield 'compound file without name' => ["\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1rest", null];
        yield 'doc name' => ['anything', 'old.doc'];
        yield 'dot name' => ['anything', 'old.DOT'];
    }

    #[DataProvider('legacy')]
    public function testLegacyWordDocumentsAreRefused(string $bytes, ?string $filename): void
    {
        $this->expectExceptionObject(UnsupportedFormat::because('The file is a Word 97–2003 binary document, or an Office document protected by a password, which this library does not read: save it as DOCX, ODT or RTF, without password.'));
        new DocumentImporter()->import($bytes, null, $filename);
    }

    public function testACompoundFileIsRefusedWhateverTheFormatGiven(): void
    {
        $this->expectException(UnsupportedFormat::class);
        new DocumentImporter()->import("\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1", Format::Docx);
    }

    /**
     * @return iterable<string, array{string, ?string, Format}>
     */
    public static function detected(): iterable
    {
        yield 'fodt name' => ['x', 'a.FODT', Format::Fodt];
        yield 'rtf name' => ['x', 'a.rtf', Format::Rtf];
        yield 'pdf name' => ['x', 'a.pdf', Format::Pdf];
        yield 'pdf bytes' => ['%PDF-1.7', null, Format::Pdf];
        yield 'rtf bytes' => ["\u{FEFF}\r\n{\\rtf1 x}", null, Format::Rtf];
        yield 'flat bytes' => ['<?xml version="1.0"?><office:document xmlns:office="o" office:version="1.3" office:mimetype="application/vnd.oasis.opendocument.text">', 'upload.bin', Format::Fodt];
        yield 'flat spreadsheet is html' => ['<office:document office:mimetype="application/vnd.oasis.opendocument.spreadsheet">', null, Format::Html];
        yield 'flat after the first 4 KiB is html' => ['<!--'.str_repeat(' ', 4096).'--><office:document office:mimetype="application/vnd.oasis.opendocument.text">', null, Format::Html];
    }

    #[DataProvider('detected')]
    public function testTheNewFormatsAreDetected(string $bytes, ?string $filename, Format $format): void
    {
        self::assertSame($format, Format::detect($bytes, $filename));
    }

    public function testTheStyleMapComesBeforeTheBuiltInRules(): void
    {
        $map = new StyleMap(['Chapter Title' => StyleRole::Heading1, ' author note ' => StyleRole::Omit, 'Q' => StyleRole::Code, '12' => StyleRole::Quote])->with('Heading 1', StyleRole::Paragraph);
        $docx = Docx::body(
            $this->docxParagraph('Ch', 'Chapter')
            .$this->docxParagraph('An', 'Note')
            .$this->docxParagraph('Child', 'Under chapter')
            .$this->docxParagraph('Heading1', 'Demoted')
            .$this->docxParagraph('Q', 'code by id')
            .$this->docxParagraph('12', 'numeric id')
            .$this->docxParagraph('Undefined', 'by undefined id')
            .$this->docxParagraph('Numbered', 'Numbered but mapped'),
        )->styles(
            '<w:style w:type="paragraph" w:styleId="Ch"><w:name w:val="Chapter Title"/></w:style>'
            .'<w:style w:type="paragraph" w:styleId="An"><w:name w:val="Author Note"/></w:style>'
            .'<w:style w:type="paragraph" w:styleId="Child"><w:name w:val="Chapter Child"/><w:basedOn w:val="Ch"/></w:style>'
            .'<w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/></w:style>'
            .'<w:style w:type="paragraph" w:styleId="Q"><w:name w:val="Something"/></w:style>'
            .'<w:style w:type="paragraph" w:styleId="12"><w:name w:val="Numbered style"/></w:style>'
            .'<w:style w:type="paragraph" w:styleId="Numbered"><w:name w:val="Chapter Title 2"/><w:basedOn w:val="Ch"/><w:pPr><w:numPr><w:numId w:val="1"/></w:numPr></w:pPr></w:style>',
        )->numbering('<w:abstractNum w:abstractNumId="0"><w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/></w:lvl></w:abstractNum><w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num>');
        $imported = new DocumentImporter(styles: $map)->import($docx->bytes(), Format::Docx);

        self::assertSame([
            ['header', ['text' => 'Chapter', 'level' => 1]],
            ['header', ['text' => 'Under chapter', 'level' => 1]],
            ['paragraph', ['text' => 'Demoted']],
            ['code', ['code' => 'code by id']],
            ['quote', ['text' => 'numeric id', 'caption' => '', 'alignment' => 'left']],
            ['paragraph', ['text' => 'by undefined id']],
            ['header', ['text' => 'Numbered but mapped', 'level' => 1]],
        ], $this->blocks($imported));
        self::assertSame([['style', 'Author Note', 1]], $this->report($imported));
        self::assertSame(StyleRole::Code, $map->role(' q '));
        self::assertNull($map->role('Other'));
        self::assertFalse($map->isEmpty());
        self::assertTrue(new StyleMap()->isEmpty());
    }

    public function testAnUndefinedStyleCanBeMappedByItsIdentifier(): void
    {
        $imported = new DocumentImporter(styles: new StyleMap(['Ghost' => StyleRole::Heading3]))->import(Docx::body($this->docxParagraph('Ghost', 'Ghost text'))->bytes(), Format::Docx);

        self::assertSame([['header', ['text' => 'Ghost text', 'level' => 3]]], $this->blocks($imported));
    }

    public function testTheStyleMapAppliesToOpenDocumentStyles(): void
    {
        $map = new StyleMap(['Chapter Title' => StyleRole::Heading2, 'Note_20_Style' => StyleRole::Omit, 'Heading 1' => StyleRole::Quote, 'Title' => StyleRole::Paragraph]);
        $imported = new DocumentImporter(styles: $map)->import(Odt::text(
            '<text:p text:style-name="Chapter">Chapter</text:p><text:p text:style-name="Note_20_Style">Gone</text:p>'
            .'<text:h text:style-name="Heading_20_1" text:outline-level="1">Heading as quote</text:h><text:h text:outline-level="2">Plain heading</text:h>'
            .'<text:p text:style-name="Title">Title as paragraph</text:p><text:p text:style-name="Child">Child of chapter</text:p>',
        )->styles(
            '<style:style style:name="Chapter" style:display-name="Chapter Title" style:family="paragraph"/>'
            .'<style:style style:name="Note_20_Style" style:family="paragraph"/>'
            .'<style:style style:name="Heading_20_1" style:display-name="Heading 1" style:family="paragraph"/>'
            .'<style:style style:name="Title" style:family="paragraph"/>'
            .'<style:style style:name="Child" style:family="paragraph" style:parent-style-name="Chapter"/>',
        )->bytes(), Format::Odt);

        self::assertSame([
            ['header', ['text' => 'Chapter', 'level' => 2]],
            ['quote', ['text' => 'Heading as quote', 'caption' => '', 'alignment' => 'left']],
            ['header', ['text' => 'Plain heading', 'level' => 2]],
            ['paragraph', ['text' => 'Title as paragraph']],
            ['header', ['text' => 'Child of chapter', 'level' => 2]],
        ], $this->blocks($imported));
        self::assertSame([['style', 'Note Style', 1]], $this->report($imported));
    }

    public function testTwoDifferentImagesOfTheSameNameAreToldApart(): void
    {
        $other = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
        $imported = new DocumentImporter()->import(Odt::text(
            '<text:p><draw:frame><draw:image xlink:href="Pictures/a/dot.png"/></draw:frame></text:p>'
            .'<text:p><draw:frame><draw:image xlink:href="Pictures/b/dot.png"/></draw:frame></text:p>'
            .'<text:p><draw:frame><draw:image xlink:href="Pictures/a/dot.png"/></draw:frame></text:p>'
            .'<text:p><draw:frame><draw:image xlink:href="Pictures/c/dot"/></draw:frame></text:p>',
        )->file('Pictures/a/dot.png', Png::bytes())->file('Pictures/b/dot.png', $other)->file('Pictures/c/dot', $other.' ')->bytes(), Format::Odt);

        self::assertSame(['media/dot.png', 'media/dot-2.png', 'media/dot-3.png'], array_keys($imported->media));
        self::assertSame(['media/dot.png', 'media/dot-2.png', 'media/dot.png', 'media/dot-3.png'], array_map($this->url(...), $imported->document->blocks));
        self::assertSame('Pictures/b/dot-2.png', $imported->media['media/dot-2.png']->path);
        self::assertSame('dot-3', new Media('dot', '')->numbered(3)->path);
    }

    public function testImagesOfAListItemComeAfterTheList(): void
    {
        $frame = '<draw:frame><draw:image xlink:href="Pictures/dot.png"/><svg:title>In item</svg:title></draw:frame>';
        $imported = new DocumentImporter()->import(Odt::text(
            '<text:list><text:list-item><text:p>One'.$frame.'</text:p></text:list-item><text:list-item><text:p>Two</text:p></text:list-item></text:list>'
            .'<text:p>After</text:p>'
            .'<table:table><table:table-row><table:table-cell><text:p>Cell<draw:frame><draw:image xlink:href="https://example.com/a.png"/></draw:frame></text:p></table:table-cell></table:table-row></table:table>',
        )->file('Pictures/dot.png', Png::bytes())->bytes(), Format::Odt);

        self::assertSame(['list', 'image', 'paragraph', 'table', 'image'], array_map(static fn (Block $block): string => $block->type, $imported->document->blocks));
        self::assertSame('In item', $imported->document->blocks[1]->data['alt']);
        self::assertSame('https://example.com/a.png', $this->url($imported->document->blocks[4]));
    }

    public function testAnImageOfANoteComesAfterItsParagraphEvenAfterAList(): void
    {
        $imported = new DocumentImporter()->import(Odt::text(
            '<text:list><text:list-item><text:p>Item</text:p></text:list-item></text:list>'
            .'<text:p>Text<text:note text:note-class="footnote"><text:note-body><text:p>Note<draw:frame><draw:image xlink:href="Pictures/dot.png"/></draw:frame></text:p></text:note-body></text:note></text:p>',
        )->file('Pictures/dot.png', Png::bytes())->bytes(), Format::Odt);

        self::assertSame(['list', 'paragraph', 'image', 'footnote'], array_map(static fn (Block $block): string => $block->type, $imported->document->blocks));
    }

    public function testAnImageThatDiffersFromEveryNumberedCopyIsReported(): void
    {
        $out = new Builder(new MediaFolder(), new SvgSanitizer());
        $first = $out->store(new Media('x.png', Png::bytes()));

        self::assertSame('media/x.png', $first);
        self::assertSame('media/x-2.png', $out->store(new Media('y/x.png', Png::bytes().'1')));
        self::assertSame('media/x.png', $out->store(new Media('z/x.png', Png::bytes())));

        $same = new Builder(new class implements MediaStore {
            public function store(Media $media): string
            {
                return 'one.png';
            }
        }, new SvgSanitizer());
        self::assertSame('one.png', $same->store(new Media('a.png', Png::bytes())));
        self::assertNull($same->store(new Media('b.png', Png::bytes().'x')));
        self::assertSame([['image', 'same name', 1]], array_map(static fn (array $row): array => [$row['what'], $row['detail'], $row['count']], $same->report->toArray()));
    }

    private function url(Block $block): ?string
    {
        $file = $block->data['file'] ?? null;

        return \is_array($file) && \is_string($file['url'] ?? null) ? $file['url'] : null;
    }

    private function docxParagraph(string $style, string $text): string
    {
        return '<w:p><w:pPr><w:pStyle w:val="'.$style.'"/></w:pPr><w:r><w:t>'.$text.'</w:t></w:r></w:p>';
    }

    /**
     * A package with namespaces replaced in every entry.
     *
     * @param array<string, string> $namespaces
     */
    private function rewrite(string $package, array $namespaces): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'strict');
        file_put_contents($path, $package);
        $zip = new ZipArchive();
        $zip->open($path);

        $entries = [];

        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $entries[(string) $zip->getNameIndex($index)] = strtr((string) $zip->getFromIndex($index), $namespaces);
        }

        $zip->close();
        unlink($path);

        return Zip::of($entries);
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

    /**
     * @return list<array{string, string, int}>
     */
    private function report(Imported $imported): array
    {
        return array_map(static fn (array $row): array => [$row['what'], $row['detail'], $row['count']], $imported->report->toArray());
    }
}
