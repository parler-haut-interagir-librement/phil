<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Tests\Functional;

use Phil\DocumentImport\DocumentExporter;
use Phil\DocumentImport\DocumentImporter;
use Phil\DocumentImport\Exception\UnsupportedFormat;
use Phil\DocumentImport\Format;
use Phil\DocumentImportBundle\Application\DocumentImports;
use Phil\DocumentImportBundle\Domain\Event\DocumentImported;
use Phil\DocumentImportBundle\PhilDocumentImportBundle;
use Phil\SvgSanitizer\SvgSanitizer;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * The container the bundle gives, compiled, its images under a prefix of its own.
 */
#[CoversClass(PhilDocumentImportBundle::class)]
#[CoversClass(DocumentImports::class)]
final class DocumentImportBundleTest extends TestCase
{
    public function testTheImporterSanitizesTheSvgImagesItStores(): void
    {
        $importer = $this->container()->get(DocumentImporter::class);
        self::assertInstanceOf(DocumentImporter::class, $importer);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><g><rect fill="red"/></g></svg>';
        $imported = $importer->import('<p>Text</p><img src="data:image/svg+xml,'.rawurlencode($svg).'" alt="Drawing">', Format::Html);

        self::assertSame(['/uploads/image-1.svg'], array_keys($imported->media));
        self::assertSame('<svg xmlns="http://www.w3.org/2000/svg"><g><rect fill="red"/></g></svg>', $imported->media['/uploads/image-1.svg']->contents);
        self::assertSame(1, $imported->report->count());
    }

    public function testTheSanitizerFollowsTheConfiguredPolicy(): void
    {
        $sanitizer = $this->container()->get(SvgSanitizer::class);
        self::assertInstanceOf(SvgSanitizer::class, $sanitizer);

        self::assertTrue($sanitizer->sanitize('<svg><g><g><g/></g></g></svg>')->isRefused());
        self::assertFalse($sanitizer->sanitize('<svg><g><g/></g></svg>')->isRefused());
    }

    public function testTheConfiguredImportsReadTheStyleMapAndDispatchTheReport(): void
    {
        $dispatcher = new EventDispatcher();
        $reports = [];
        $dispatcher->addListener(DocumentImported::class, static function (DocumentImported $event) use (&$reports): void {
            $reports[(string) $event->filename] = $event->imported->report->summary();
        });
        $container = $this->container($dispatcher);
        $imports = $container->get(DocumentImports::class);
        self::assertInstanceOf(DocumentImports::class, $imports);

        $imported = $imports->import('{\rtf1\ansi{\stylesheet{\s1 Chapter Title;}{\s2 Author Note;}}\pard\s1 One\par\pard\s2 Hidden\par}', filename: 'one.rtf');
        $blocks = $imports->documentWithReport($imported)->blocks;

        self::assertSame(['header', 'warning'], array_map(static fn (Block $block): string => $block->type, $blocks));
        self::assertSame('Left out', $blocks[1]->data['title']);
        self::assertSame(['one.rtf' => ['Paragraphs of styles the application leaves out are not imported: Author Note (1).']], $reports);

        $this->expectException(UnsupportedFormat::class);
        $imports->import('%PDF-1.7', filename: 'scan.pdf');
    }

    public function testTheExporterWritesWhatTheImporterReadsAgain(): void
    {
        $container = $this->container();
        $exporter = $container->get(DocumentExporter::class);
        $importer = $container->get(DocumentImporter::class);
        self::assertInstanceOf(DocumentExporter::class, $exporter);
        self::assertInstanceOf(DocumentImporter::class, $importer);

        $exported = $exporter->export(new Document([new Block('a', 'header', ['text' => 'Title', 'level' => 2])]), Format::Docx);

        self::assertSame([['text' => 'Title', 'level' => 2]], array_map(static fn (Block $block): array => $block->data, $importer->import($exported->bytes, Format::Docx)->document->blocks));
    }

    private function container(?EventDispatcher $dispatcher = null): ContainerBuilder
    {
        $extension = new PhilDocumentImportBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);
        $container = new ContainerBuilder();

        if ($dispatcher instanceof EventDispatcher) {
            $container->register('event_dispatcher', EventDispatcher::class)->setSynthetic(true)->setPublic(true);
        }

        $extension->load([[
            'media' => ['prefix' => '/uploads/'],
            'svg' => ['max_depth' => 3],
            'formats' => ['import' => ['rtf', 'html', 'docx']],
            'styles' => ['Chapter Title' => 'heading-1', 'author note' => 'omit'],
            'report' => ['title' => 'Left out'],
        ]], $container);
        $container->compile(true);

        if ($dispatcher instanceof EventDispatcher) {
            $container->set('event_dispatcher', $dispatcher);
        }

        return $container;
    }
}
