<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit;

use Phil\DocumentImport\DocumentImporter;
use Phil\DocumentImport\Exception\InvalidDocument;
use Phil\DocumentImport\Exception\LimitExceeded;
use Phil\DocumentImport\Format;
use Phil\DocumentImport\Internal\Archive;
use Phil\DocumentImport\Internal\Zip;
use Phil\DocumentImport\Limits;
use Phil\DocumentImport\Media;
use Phil\DocumentImport\MediaStore;
use Phil\DocumentImport\Tests\Unit\Fixture\Docx;
use Phil\DocumentImport\Tests\Unit\Fixture\Odt;
use Phil\DocumentImport\Tests\Unit\Fixture\Png;
use Phil\Markup\Reader\MarkupReader;
use Phil\Markup\Reader\Reading;
use Phil\SvgSanitizer\Policy;
use Phil\SvgSanitizer\SvgSanitizer;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\UnknownBlockType;
use Phil\Ujo\Normalizer;
use Phil\Ujo\Type\BlockTypeRegistry;
use Phil\Ujo\Type\StandardTypes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZipArchive;

#[CoversClass(DocumentImporter::class)]
#[CoversClass(Archive::class)]
#[CoversClass(Zip::class)]
final class ImporterTest extends TestCase
{
    public function testMarkdownIsReadByPhilMarkup(): void
    {
        $imported = new DocumentImporter()->import("---\ntitle: Notes\n---\n# Title\n\nText with *emphasis*.[^1]\n\n[^1]: A note.\n", filename: 'notes.md');

        self::assertSame(Format::Markdown, $imported->format);
        self::assertSame(['title' => 'Notes'], $imported->metadata);
        self::assertSame(['header', 'paragraph', 'footnote'], array_map(static fn (Block $block): string => $block->type, $imported->document->blocks));
        self::assertTrue($imported->report->isEmpty());
        self::assertSame([], $imported->media);
    }

    public function testTheMarkdownReaderCanBeGiven(): void
    {
        $reader = new class implements MarkupReader {
            public function read(string $source): Reading
            {
                return new Reading(new Document(), ['source' => $source]);
            }
        };

        self::assertSame(['source' => '# Hi'], new DocumentImporter(markdown: $reader)->import('# Hi', Format::Markdown)->metadata);
    }

    public function testHtmlImagesInDataAddressesBecomeMedia(): void
    {
        $html = '<html><head><title>Pasted</title><meta name="author" content="Ann"></head><body><p>Text<script>alert(1)</script></p>'
            .'<img src="https://images.test/first.png"><img src="data:image/png;base64,'.Png::BASE64.'" alt="Dot"><img src="DATA:image/png;base64,'.chunk_split(Png::BASE64, 10, ' ').'">'
            .'<img src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%2F%3E" alt="Vector">'
            .'<img src="data:image/png;base64,!!!"><img src="data:,plain"><img src="https://images.test/a.png" alt="Remote">'
            .'<form><input name="q"><button>Go</button></form><iframe src="https://a.test/"></iframe><video></video><p>Done'."\0".'</p></body></html>';

        $imported = new DocumentImporter()->import($html);

        $images = array_values(array_filter($imported->document->blocks, static fn (Block $block): bool => 'image' === $block->type));
        $image = static fn (string $url, string $alt): array => ['file' => ['url' => $url], 'caption' => '', ...'' === $alt ? [] : ['alt' => $alt], 'withBorder' => false, 'withBackground' => false, 'stretched' => false];

        self::assertSame(Format::Html, $imported->format);
        self::assertSame(['title' => 'Pasted', 'author' => 'Ann'], $imported->metadata);
        self::assertSame([$image('https://images.test/first.png', ''), $image('media/image-1.png', 'Dot'), $image('media/image-2.png', ''), $image('media/image-3.svg', 'Vector'), $image('https://images.test/a.png', 'Remote')], array_map(static fn (Block $block): array => $block->data, $images));
        self::assertSame(['media/image-1.png', 'media/image-2.png', 'media/image-3.svg'], array_keys($imported->media));
        self::assertSame(Png::bytes(), $imported->media['media/image-2.png']->contents);
        self::assertSame('Dot', $imported->media['media/image-1.png']->description);
        self::assertSame('<svg xmlns="http://www.w3.org/2000/svg"/>', $imported->media['media/image-3.svg']->contents);
        self::assertSame([
            ['element', 'script', 1],
            ['element', 'form', 1],
            ['element', 'input', 1],
            ['element', 'button', 1],
            ['element', 'iframe', 1],
            ['element', 'video', 1],
            ['image', 'application/octet-stream', 2],
        ], array_map(static fn (array $row): array => [$row['what'], $row['detail'], $row['count']], $imported->report->toArray()));
        self::assertStringContainsString("Done\u{FFFD}", json_encode($imported->document->toArray(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE));
    }

    public function testSvgImagesAreSanitizedBeforeTheyAreStored(): void
    {
        $svg = static fn (string $svg): string => '<img src="data:image/svg+xml,'.rawurlencode($svg).'" alt="Vector">';
        $html = '<p>Text</p>'
            .$svg('<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(2)</script><use href="https://x.test/a.svg#b"/><rect fill="red"/></svg>')
            .$svg('<svg xmlns="http://www.w3.org/2000/svg"><g><rect/></g></svg>')
            .$svg('<svg xmlns="http://www.w3.org/2000/svg"><g></svg>');

        $imported = new DocumentImporter(svg: new SvgSanitizer(new Policy(maxDepth: 2)))->import($html, Format::Html);

        self::assertSame(['media/image-1.svg'], array_keys($imported->media));
        self::assertSame('<svg xmlns="http://www.w3.org/2000/svg"><use/><rect fill="red"/></svg>', $imported->media['media/image-1.svg']->contents);
        self::assertSame('image/svg+xml', $imported->media['media/image-1.svg']->mediaType);
        self::assertSame('Vector', $imported->media['media/image-1.svg']->description);
        self::assertSame('image-1.svg', $imported->media['media/image-1.svg']->path);
        self::assertSame([
            ['svg-content', 'event_handler', 1],
            ['svg-content', 'denied_element', 1],
            ['svg-content', 'external_reference', 1],
            ['image', 'application/octet-stream', 2],
        ], array_map(static fn (array $row): array => [$row['what'], $row['detail'], $row['count']], $imported->report->toArray()));
        self::assertSame('Scripts, links to other files and other unsafe parts of SVG images were removed.', $imported->report->omissions()[0]->what->description());
    }

    public function testTheHtmlReaderCanBeGiven(): void
    {
        $reader = new class implements MarkupReader {
            public string $source = '';

            public function read(string $source): Reading
            {
                $this->source = $source;

                return new Reading(new Document(), ['seen' => true]);
            }
        };

        $imported = new DocumentImporter(html: $reader)->import('<p>Hi</p>', Format::Html);

        self::assertSame(['seen' => true], $imported->metadata);
        self::assertSame('<html><head></head><body><p>Hi</p></body></html>', $reader->source);
    }

    public function testTheMediaStoreGivesTheAddresses(): void
    {
        $store = new class implements MediaStore {
            /** @var list<string> */
            public array $stored = [];

            public function store(Media $media): string
            {
                $this->stored[] = $media->path;

                return 'https://cdn.test/'.hash('sha256', $media->contents).'.png';
            }
        };

        $imported = new DocumentImporter($store)->import(Odt::text('<text:p><draw:frame><draw:image xlink:href="Pictures/dot.png"/></draw:frame></text:p>')->file('Pictures/dot.png', Png::bytes())->bytes());

        self::assertSame(['Pictures/dot.png'], $store->stored);
        self::assertSame(['https://cdn.test/'.hash('sha256', Png::bytes()).'.png'], array_keys($imported->media));
    }

    public function testTheNormalizerCanBeGiven(): void
    {
        $normalizer = new Normalizer(new BlockTypeRegistry(StandardTypes::paragraph()));

        self::assertSame('paragraph', new DocumentImporter(normalizer: $normalizer)->import(Docx::body('<w:p><w:r><w:t>a</w:t></w:r></w:p>')->bytes(), Format::Docx)->document->blocks[0]->type);

        $this->expectException(UnknownBlockType::class);

        new DocumentImporter(normalizer: $normalizer)->import(Docx::body('<w:p><w:r><w:br w:type="page"/></w:r></w:p>')->bytes(), Format::Docx);
    }

    public function testFilesAreReadFromTheirPath(): void
    {
        $path = sys_get_temp_dir().'/phil-import-test.odt';
        file_put_contents($path, Odt::text('<text:p>From disk</text:p>')->bytes());

        try {
            $imported = new DocumentImporter()->importFile($path);
            $forced = new DocumentImporter()->importFile($path, Format::Odt);
        } finally {
            unlink($path);
        }

        self::assertSame(Format::Odt, $imported->format);
        self::assertSame('From disk', $imported->document->blocks[0]->data['text']);
        self::assertSame(Format::Odt, $forced->format);
    }

    public function testTheTemporaryFileIsRemoved(): void
    {
        $before = glob(sys_get_temp_dir().'/phil-import*');
        new DocumentImporter()->import(Odt::text('<text:p>a</text:p>')->bytes());

        try {
            new DocumentImporter()->import('not a zip', Format::Odt);
        } catch (InvalidDocument) {
        }

        self::assertSame($before, glob(sys_get_temp_dir().'/phil-import*'));
    }

    public function testACorruptedEntryIsRefused(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'corrupt');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('content.xml', '<marker-of-the-entry/>');
        $zip->setCompressionName('content.xml', ZipArchive::CM_STORE);
        $zip->close();

        $bytes = str_replace('marker-of-the-entry', 'marker-of-the-ENTRY', (string) file_get_contents($path));
        unlink($path);

        $handler = static fn (): bool => true;
        set_error_handler($handler);

        try {
            new DocumentImporter()->import($bytes, Format::Odt);
            self::fail('A corrupted entry is read.');
        } catch (InvalidDocument $invalidDocument) {
            self::assertSame('The entry "content.xml" of the archive cannot be read.', $invalidDocument->getMessage());
        } finally {
            self::assertSame($handler, set_error_handler(null));
            restore_error_handler();
            restore_error_handler();
        }
    }

    public function testAMissingFileIsRefused(): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('The file "'.sys_get_temp_dir().'" cannot be read.');

        new DocumentImporter()->importFile(sys_get_temp_dir());
    }

    public function testBytesThatAreNotAnArchiveAreRefused(): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('The file is not a ZIP archive.');

        new DocumentImporter()->import('not a zip', Format::Docx);
    }

    public function testAnEntryLargerThanTheLimitIsRefused(): void
    {
        $bytes = Docx::body(str_repeat('<w:p/>', 1000))->bytes();
        $size = $this->sizes($bytes)['word/document.xml'];
        new DocumentImporter(limits: new Limits($size, 1000000))->import($bytes, Format::Docx);

        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('The entry "word/document.xml" is larger than '.($size - 1).' bytes once uncompressed.');

        new DocumentImporter(limits: new Limits($size - 1, 1000000))->import($bytes, Format::Docx);
    }

    public function testAnArchiveLargerThanTheLimitIsRefused(): void
    {
        $bytes = Docx::body(str_repeat('<w:p/>', 1000))->bytes();
        $total = array_sum($this->sizes($bytes));
        new DocumentImporter(limits: new Limits(1000000, $total))->import($bytes, Format::Docx);

        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('The archive is larger than '.($total - 1).' bytes once uncompressed.');

        new DocumentImporter(limits: new Limits(1000000, $total - 1))->import($bytes, Format::Docx);
    }

    /**
     * @return array<string, int>
     */
    private function sizes(string $bytes): array
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'size');
        file_put_contents($path, $bytes);
        $zip = new ZipArchive();
        $zip->open($path);

        $sizes = [];

        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $name = $zip->getNameIndex($index);
            $contents = $zip->getFromIndex($index);

            if (false !== $name && false !== $contents) {
                $sizes[$name] = \strlen($contents);
            }
        }

        $zip->close();
        unlink($path);

        return $sizes;
    }
}
