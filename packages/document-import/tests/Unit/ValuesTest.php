<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit;

use Phil\DocumentImport\Exception\UnsupportedFormat;
use Phil\DocumentImport\Format;
use Phil\DocumentImport\Imported;
use Phil\DocumentImport\Limits;
use Phil\DocumentImport\Media;
use Phil\DocumentImport\MediaFolder;
use Phil\DocumentImport\Omitted;
use Phil\DocumentImport\Report;
use Phil\DocumentImport\Tests\Unit\Fixture\Odt;
use Phil\DocumentImport\Tests\Unit\Fixture\Png;
use Phil\DocumentImport\Tests\Unit\Fixture\Zip;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Format::class)]
#[CoversClass(Limits::class)]
#[CoversClass(Media::class)]
#[CoversClass(MediaFolder::class)]
#[CoversClass(Omitted::class)]
#[CoversClass(Report::class)]
#[CoversClass(Imported::class)]
final class ValuesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?Format}>
     */
    public static function filenames(): iterable
    {
        foreach (['g.fodt' => Format::Fodt, 'h.RTF' => Format::Rtf, 'i.pdf' => Format::Pdf, 'a.docx' => Format::Docx, 'A.DOCM' => Format::Docx, 'b.dotx' => Format::Docx, 'b.dotm' => Format::Docx, 'c.odt' => Format::Odt, 'c.ott' => Format::Odt, 'd.md' => Format::Markdown, 'd.markdown' => Format::Markdown, 'd.mdown' => Format::Markdown, 'd.mkd' => Format::Markdown, 'e.html' => Format::Html, 'e.htm' => Format::Html, 'e.xhtml' => Format::Html, 'f.txt' => null, 'docx' => null] as $name => $format) {
            yield $name => [$name, $format];
        }
    }

    #[DataProvider('filenames')]
    public function testTheFileNameAnnouncesTheFormat(string $filename, ?Format $format): void
    {
        self::assertSame($format, Format::fromFilename($filename));
    }

    public function testTheBytesShowTheFormatWhenTheNameDoesNot(): void
    {
        $odt = Odt::text('')->bytes();

        self::assertSame(Format::Odt, Format::detect($odt));
        self::assertSame(Format::Odt, Format::detect($odt, 'upload.bin'));
        self::assertSame(Format::Markdown, Format::detect($odt, 'notes.md'));
        self::assertSame(Format::Docx, Format::detect(Zip::of(['mimetype' => 'application/vnd.oasis.opendocument.spreadsheet'])));
        self::assertSame(Format::Docx, Format::detect(Zip::of(['[Content_Types].xml' => '<Types/>'])));
        self::assertSame(Format::Html, Format::detect("\u{FEFF} \n\t\r<p>x</p>"));
    }

    public function testUnknownBytesAreRefused(): void
    {
        $this->expectException(UnsupportedFormat::class);
        $this->expectExceptionMessageIsOrContains('The format of "notes.txt" is not recognised: give it, or a file name ending with .docx, .odt, .fodt, .rtf, .pdf, .md or .html.');

        Format::detect('# Title', 'notes.txt');
    }

    public function testUnknownBytesWithoutNameAreRefused(): void
    {
        $this->expectException(UnsupportedFormat::class);
        $this->expectExceptionMessageIsOrContains('The format of the file is not recognised');

        Format::detect('x<p>');
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function images(): iterable
    {
        yield 'png' => [Png::bytes(), 'image/png', true];
        yield 'jpeg' => ["\xFF\xD8\xFF\xE0", 'image/jpeg', true];
        yield 'gif' => ['GIF89a', 'image/gif', true];
        yield 'webp' => ['RIFF0000WEBPVP8 ', 'image/webp', true];
        yield 'riff without webp' => ['RIFF0000WAVE', 'application/octet-stream', false];
        yield 'avif' => ["\0\0\0\x20ftypavif\0\0", 'image/avif', true];
        yield 'avif sequence' => ["\0\0\0\x20ftypavis\0\0", 'image/avif', true];
        yield 'bmp' => ['BM....', 'image/bmp', true];
        yield 'tiff little endian' => ["II*\0", 'image/tiff', false];
        yield 'tiff big endian' => ["MM\0*", 'image/tiff', false];
        yield 'emf' => [str_repeat("\1", 40).' EMF'."\0\0", 'image/emf', false];
        yield 'svg at the end of the first kilobyte' => [str_repeat(' ', 1020).'<svg/>', 'image/svg+xml', true];
        yield 'svg past the first kilobyte' => [str_repeat(' ', 1021).'<svg/>', 'application/octet-stream', false];
        yield 'wmf' => ["\xD7\xCD\xC6\x9A", 'image/wmf', false];
        yield 'svg' => ["  \n<svg xmlns=\"http://www.w3.org/2000/svg\"/>", 'image/svg+xml', true];
        yield 'svg with declaration' => ['<?xml version="1.0"?><svg/>', 'image/svg+xml', true];
        yield 'xml without svg' => ['<?xml version="1.0"?><html/>', 'application/octet-stream', false];
        yield 'svg too far' => ['<?xml version="1.0"?>'.str_repeat(' ', 1024).'<svg/>', 'application/octet-stream', false];
        yield 'text' => ['hello', 'application/octet-stream', false];
    }

    #[DataProvider('images')]
    public function testTheMediaTypeIsReadFromTheBytes(string $bytes, string $type, bool $web): void
    {
        $media = new Media('word/media/image1.png', $bytes, 'Alt');

        self::assertSame($type, $media->mediaType);
        self::assertSame($web, $media->isWebImage());
        self::assertSame('Alt', $media->description);
        self::assertSame($bytes, $media->contents);
    }

    public function testTheFileNameIsSafe(): void
    {
        self::assertSame('image-1_a.b.png', new Media('Pictures/sub dir/image 1_a.b.png', '')->filename());
        self::assertSame('caf--.png', new Media("caf\u{E9}.png", '')->filename());
        self::assertSame('media/x.png', new MediaFolder()->store(new Media('a/x.png', '')));
        self::assertSame('/uploads/2026/x.png', new MediaFolder('/uploads/2026/')->store(new Media('a/x.png', '')));
    }

    public function testTheFileNameEndsWithTheTypeOfTheBytes(): void
    {
        $png = "\x89PNG\r\n\x1A\n".str_repeat("\0", 16);
        $jpeg = "\xFF\xD8\xFF\xE0";

        self::assertSame('chart.html.png', new Media('word/media/chart.html', $png)->filename());
        self::assertSame('chart.PNG', new Media('word/media/chart.PNG', $png)->filename());
        self::assertSame('photo.jpeg', new Media('photo.jpeg', $jpeg)->filename());
        self::assertSame('photo.JPG', new Media('photo.JPG', $jpeg)->filename());
        self::assertSame('photo.jpe', new Media('photo.jpe', $jpeg)->filename());
        self::assertSame('photo.png.jpg', new Media('photo.png', $jpeg)->filename());
        self::assertSame('image.jpg', new Media('image', $jpeg)->filename());
        self::assertSame('drawing.png.svg', new Media('drawing.png', '<svg xmlns="http://www.w3.org/2000/svg"/>')->filename());
        self::assertSame('a.gif', new Media('a', 'GIF89a')->filename());
        self::assertSame('a.webp', new Media('a', 'RIFF....WEBPVP8 ')->filename());
        self::assertSame('a.avif', new Media('a', "\0\0\0\x1CftypavifX")->filename());
        self::assertSame('a.bmp', new Media('a', 'BM')->filename());
        self::assertSame('a.emf', new Media('a.emf', str_repeat("\0", 40).' EMF')->filename());
        self::assertSame('media/x.svg.png', new MediaFolder()->store(new Media('a/x.svg', $png)));
    }

    public function testTheDefaultLimits(): void
    {
        $limits = new Limits();

        self::assertSame(67108864, $limits->entryBytes);
        self::assertSame(268435456, $limits->totalBytes);
    }

    public function testTheReportCountsByKindAndDetail(): void
    {
        $report = new Report();

        self::assertTrue($report->isEmpty());
        self::assertSame(0, $report->count());

        $report->add(Omitted::Formatting, 'colour');
        $report->add(Omitted::Comment);
        $report->add(Omitted::Formatting, 'font');
        $report->add(Omitted::Formatting, 'colour');

        self::assertFalse($report->isEmpty());
        self::assertSame(4, $report->count());
        self::assertSame(3, $report->count(Omitted::Formatting));
        self::assertSame(0, $report->count(Omitted::Macro));
        self::assertSame([
            ['what' => 'formatting', 'detail' => 'colour', 'count' => 2, 'description' => 'Colours, fonts and sizes are not imported.'],
            ['what' => 'formatting', 'detail' => 'font', 'count' => 1, 'description' => 'Colours, fonts and sizes are not imported.'],
            ['what' => 'comment', 'detail' => '', 'count' => 1, 'description' => 'Comments and annotations are not imported.'],
        ], $report->toArray());
        self::assertSame(Omitted::Formatting, $report->omissions()[0]->what);
    }

    public function testEveryOmissionIsDescribed(): void
    {
        $descriptions = array_map(static fn (Omitted $omitted): string => $omitted->description(), Omitted::cases());

        self::assertCount(\count(Omitted::cases()), array_unique($descriptions));
        self::assertSame([
            'Comments and annotations are not imported.',
            'Tracked changes are imported as accepted: deleted text is left out.',
            'Page headers and footers are not imported.',
            'Text boxes and frames are not imported.',
            'Shapes, charts and diagrams are not imported.',
            'Equations are not imported.',
            'Embedded objects are not imported.',
            'Some images could not be read.',
            'Scripts, links to other files and other unsafe parts of SVG images were removed.',
            'Tables inside table cells are imported as their text.',
            'Merged cells are split: the cells they covered are empty.',
            'Links to places inside the document keep their text but lose their target.',
            'Hidden text is not imported.',
            'Colours, fonts and sizes are not imported.',
            'Macros are never run nor imported.',
            'Elements without a place in a block document are not imported.',
            'Paragraphs of styles the application leaves out are not imported.',
            'Blocks without a place in a word processing document are not exported.',
            'Form fields, stamps and other annotations of the page are not imported.',
        ], $descriptions);
    }

    public function testTheReportReadsAsSentencesAndAsABlock(): void
    {
        $report = new Report();
        $report->add(Omitted::Formatting, 'colour');
        $report->add(Omitted::Formatting, 'colour');
        $report->add(Omitted::Formatting, 'font');
        $report->add(Omitted::Comment);
        $report->add(Omitted::Comment);
        $report->add(Omitted::Style, 'Note <draft>');

        self::assertSame([
            'Colours, fonts and sizes are not imported: colour (2), font (1).',
            'Comments and annotations are not imported (2).',
            'Paragraphs of styles the application leaves out are not imported: Note <draft> (1).',
        ], $report->summary());
        self::assertEquals(new Block('import-report', 'warning', [
            'title' => 'What was not imported',
            'message' => 'Colours, fonts and sizes are not imported: colour (2), font (1).<br>Comments and annotations are not imported (2).<br>Paragraphs of styles the application leaves out are not imported: Note &lt;draft&gt; (1).',
        ]), $report->toBlock());
        self::assertSame(['title' => 'Ce qui &lt;manque&gt;'], array_intersect_key($report->toBlock('Ce qui <manque>', 'r')->data, ['title' => true]));
        self::assertSame('r', $report->toBlock('x', 'r')->id);
        self::assertSame([], new Report()->summary());
    }

    public function testTheReportIsRenderedWithTheDocument(): void
    {
        $report = new Report();
        $document = new Document([new Block('import-report', 'paragraph', ['text' => 'a']), new Block('import-report-2', 'paragraph', ['text' => 'b'])]);
        $empty = new Imported(Format::Html, $document, [], [], new Report());

        self::assertSame($document, $empty->documentWithReport());

        $report->add(Omitted::Macro);
        $rendered = new Imported(Format::Html, $document, [], [], $report)->documentWithReport('Left out');

        self::assertCount(3, $rendered->blocks);
        self::assertSame('import-report-3', $rendered->blocks[2]->id);
        self::assertSame(['title' => 'Left out', 'message' => 'Macros are never run nor imported (1).'], $rendered->blocks[2]->data);
        self::assertSame('import-report', new Imported(Format::Html, new Document(), [], [], $report)->documentWithReport()->blocks[0]->id);
    }
}
