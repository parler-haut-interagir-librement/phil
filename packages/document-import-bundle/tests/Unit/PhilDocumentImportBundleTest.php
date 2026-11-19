<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Tests\Unit;

use Closure;
use Phil\DocumentImport\DocumentExporter;
use Phil\DocumentImport\DocumentImporter;
use Phil\DocumentImport\Format;
use Phil\DocumentImport\Limits;
use Phil\DocumentImport\MediaFolder;
use Phil\DocumentImport\MediaStore;
use Phil\DocumentImport\StyleMap;
use Phil\DocumentImport\StyleRole;
use Phil\DocumentImportBundle\Application\Contract\DocumentScreening;
use Phil\DocumentImportBundle\Application\Contract\PendingImports;
use Phil\DocumentImportBundle\Application\DocumentImportQueue;
use Phil\DocumentImportBundle\Application\DocumentImports;
use Phil\DocumentImportBundle\Application\ImportDocumentHandler;
use Phil\DocumentImportBundle\Application\Message\ImportDocument;
use Phil\DocumentImportBundle\Infrastructure\Claim\FilesystemPendingImports;
use Phil\DocumentImportBundle\Infrastructure\DependencyInjection\DocumentImportServices;
use Phil\DocumentImportBundle\Infrastructure\Media\MediaLibraryStore;
use Phil\DocumentImportBundle\Infrastructure\Twig\ImportReportExtension;
use Phil\DocumentImportBundle\Infrastructure\UploadScan\UploadScanScreening;
use Phil\DocumentImportBundle\PhilDocumentImportBundle;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Domain\Repository\MediaRepository;
use Phil\MediaBundle\Infrastructure\Twig\MediaExtension;
use Phil\SvgSanitizer\Policy;
use Phil\SvgSanitizer\SvgSanitizer;
use Phil\UploadScanBundle\Application\UploadScanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(PhilDocumentImportBundle::class)]
#[CoversClass(DocumentImportServices::class)]
final class PhilDocumentImportBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_document_import', $this->extension()->getAlias());
        self::assertSame(['service_container', DocumentImportServices::SVG_POLICY, SvgSanitizer::class, Limits::class, MediaFolder::class, DocumentImporter::class, StyleMap::class, DocumentExporter::class, DocumentImports::class, ImportReportExtension::class], array_keys($container->getDefinitions()));
        self::assertSame([null, true, 4_194_304, 64, 50_000], $container->getDefinition(DocumentImportServices::SVG_POLICY)->getArguments());
        self::assertSame([['withLinkSchemes', ['http', 'https', 'mailto'], true]], $container->getDefinition(DocumentImportServices::SVG_POLICY)->getMethodCalls());
        self::assertEquals([new Reference(DocumentImportServices::SVG_POLICY)], $container->getDefinition(SvgSanitizer::class)->getArguments());
        self::assertTrue($container->getDefinition(SvgSanitizer::class)->isPublic());
        self::assertSame([64 * 1024 * 1024, 256 * 1024 * 1024, 100, 500_000], $container->getDefinition(Limits::class)->getArguments());
        self::assertSame(['media/'], $container->getDefinition(MediaFolder::class)->getArguments());
        self::assertSame(MediaFolder::class, (string) $container->getAlias(MediaStore::class));
        self::assertEquals([new Reference(MediaStore::class), null, null, null, new Reference(Limits::class), new Reference(SvgSanitizer::class), new Reference(StyleMap::class)], $container->getDefinition(DocumentImporter::class)->getArguments());
        self::assertTrue($container->getDefinition(DocumentImporter::class)->isPublic());
        self::assertSame([[]], $container->getDefinition(StyleMap::class)->getArguments());
        self::assertFalse($container->getDefinition(StyleMap::class)->isPublic());
        self::assertEquals([null, new Reference(SvgSanitizer::class), null], $container->getDefinition(DocumentExporter::class)->getArguments());
        self::assertTrue($container->getDefinition(DocumentExporter::class)->isPublic());
        self::assertEquals([
            new Reference(DocumentImporter::class),
            new Reference(DocumentExporter::class),
            new Reference(Limits::class),
            Format::cases(),
            [Format::Docx, Format::Odt],
            'What was not imported',
            new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            null,
        ], $container->getDefinition(DocumentImports::class)->getArguments());
        self::assertSame(['What was not imported'], $container->getDefinition(ImportReportExtension::class)->getArguments());
        self::assertSame(['twig.extension' => [[]]], $container->getDefinition(ImportReportExtension::class)->getTags());
        $dispatcher = $container->getDefinition(DocumentImports::class)->getArgument(6);
        self::assertInstanceOf(Reference::class, $dispatcher);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $dispatcher->getInvalidBehavior());
        self::assertTrue($container->getDefinition(DocumentImports::class)->isPublic());
        self::assertSame(['docx', 'odt'], PhilDocumentImportBundle::EXPORTS);
        self::assertSame('phil_document_import.svg_policy', DocumentImportServices::SVG_POLICY);
        self::assertSame('phil_document_import.media_address', DocumentImportServices::MEDIA_ADDRESS);
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load([
            'media' => ['prefix' => '/uploads/', 'collection' => 'imports'],
            'limits' => ['entry_bytes' => 10, 'total_bytes' => 20, 'depth' => 30, 'objects' => 40],
            'formats' => ['import' => ['docx', 'rtf'], 'export' => ['odt']],
            'styles' => ['Chapter Title' => 'heading-1', 'Author-Note' => 'omit', 'Rules_Box' => 'quote'],
            'report' => ['title' => 'Left out'],
            'export' => ['media_source' => 'app.media_source', 'temporary_directory' => '/var/exports'],
            'svg' => ['max_bytes' => 1, 'max_depth' => 2, 'max_elements' => 3, 'data_images' => false, 'animations' => true, 'link_schemes' => ['https', 'tel']],
        ]);

        self::assertSame([null, false, 1, 2, 3], $container->getDefinition(DocumentImportServices::SVG_POLICY)->getArguments());
        self::assertSame([['withLinkSchemes', ['https', 'tel'], true], ['withAnimations', [], true]], $container->getDefinition(DocumentImportServices::SVG_POLICY)->getMethodCalls());
        self::assertSame([10, 20, 30, 40], $container->getDefinition(Limits::class)->getArguments());
        self::assertSame([['Chapter Title' => StyleRole::Heading1, 'Author-Note' => StyleRole::Omit, 'Rules_Box' => StyleRole::Quote]], $container->getDefinition(StyleMap::class)->getArguments());
        self::assertEquals([new Reference('app.media_source'), new Reference(SvgSanitizer::class), '/var/exports'], $container->getDefinition(DocumentExporter::class)->getArguments());
        self::assertSame([[Format::Docx, Format::Rtf], [Format::Odt], 'Left out'], \array_slice($container->getDefinition(DocumentImports::class)->getArguments(), 3, 3));
        self::assertSame(['/uploads/'], $container->getDefinition(MediaFolder::class)->getArguments());
        self::assertFalse($container->hasDefinition(MediaLibraryStore::class));
    }

    public function testThePolicyIsBuiltFromTheConfiguration(): void
    {
        $container = $this->load(['svg' => ['max_depth' => 7, 'animations' => true, 'link_schemes' => ['tel']]]);
        $container->getDefinition(DocumentImportServices::SVG_POLICY)->setPublic(true);
        $container->compile(true);
        $policy = $container->get(DocumentImportServices::SVG_POLICY);

        self::assertInstanceOf(Policy::class, $policy);
        self::assertSame(7, $policy->maxDepth);
        self::assertSame(['tel' => true], $policy->linkSchemes);
        self::assertTrue($policy->allowsElement('animate'));
        self::assertInstanceOf(DocumentImporter::class, $container->get(DocumentImporter::class));
    }

    public function testWithTheMediaBundleTheImagesGoToTheMediaLibrary(): void
    {
        foreach (['auto', 'library'] as $store) {
            $container = $this->load(['media' => ['store' => $store, 'collection' => 'imports']], media: true);

            self::assertSame(MediaLibraryStore::class, (string) $container->getAlias(MediaStore::class));
            self::assertEquals([new Reference(MediaLibrary::class), new Reference(MediaRepository::class), new Reference(DocumentImportServices::MEDIA_ADDRESS), 'imports'], $container->getDefinition(MediaLibraryStore::class)->getArguments());
            self::assertSame(Closure::class, $container->getDefinition(DocumentImportServices::MEDIA_ADDRESS)->getClass());
            self::assertSame([Closure::class, 'fromCallable'], $container->getDefinition(DocumentImportServices::MEDIA_ADDRESS)->getFactory());
            self::assertEquals([[new Reference(MediaExtension::class), 'url']], $container->getDefinition(DocumentImportServices::MEDIA_ADDRESS)->getArguments());
        }

        $folder = $this->load(['media' => ['store' => 'folder']], media: true);

        self::assertSame(MediaFolder::class, (string) $folder->getAlias(MediaStore::class));
        self::assertFalse($folder->hasDefinition(MediaLibraryStore::class));
        self::assertFalse($folder->hasDefinition(DocumentImportServices::MEDIA_ADDRESS));
    }

    public function testTheLibraryStoreNeedsTheMediaBundle(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The store "library" keeps the images in phil/media-bundle, which is not registered: register PhilMediaBundle, or choose "folder".');

        $this->load(['media' => ['store' => 'library']]);
    }

    public function testBadValuesAreRefused(): void
    {
        $refused = [
            [['media' => ['store' => 'disk']], 'The value "disk" is not allowed'],
            [['media' => ['collection' => 'Imports']], 'A collection key is 1 to 64'],
            [['media' => ['collection' => 'a'.str_repeat('b', 64)]], 'A collection key is 1 to 64'],
            [['media' => ['collection' => 12]], 'A collection key is 1 to 64'],
            [['media' => ['collection' => "imports\n"]], 'A collection key is 1 to 64'],
            [['limits' => ['entry_bytes' => 0]], 'Should be greater than or equal to 1'],
            [['limits' => ['total_bytes' => 0]], 'Should be greater than or equal to 1'],
            [['svg' => ['max_bytes' => 0]], 'Should be greater than or equal to 1'],
            [['svg' => ['max_depth' => 0]], 'Should be greater than or equal to 1'],
            [['svg' => ['max_elements' => 0]], 'Should be greater than or equal to 1'],
            [['svg' => ['link_schemes' => ['javascript']]], 'A link scheme is written in lower case'],
            [['svg' => ['link_schemes' => ['HTTPS']]], 'A link scheme is written in lower case'],
            [['svg' => ['link_schemes' => ['ht tp']]], 'A link scheme is written in lower case'],
            [['svg' => ['link_schemes' => ["https\n"]]], 'A link scheme is written in lower case'],
            [['svg' => ['link_schemes' => [1]]], 'A link scheme is written in lower case'],
            [['limits' => ['depth' => 0]], 'Should be greater than or equal to 1'],
            [['limits' => ['objects' => 0]], 'Should be greater than or equal to 1'],
            [['formats' => ['import' => ['doc']]], 'The value "doc" is not allowed'],
            [['formats' => ['export' => ['pdf']]], 'The value "pdf" is not allowed'],
            [['styles' => ['Title' => 'heading-7']], 'The value "heading-7" is not allowed'],
            [['styles' => [' ' => 'omit']], 'A style is named by 1 to 255 characters'],
            [['styles' => [str_repeat('é', 256) => 'omit']], 'A style is named by 1 to 255 characters'],
            [['report' => ['title' => ' ']], 'The title of the report is a text'],
            [['report' => ['title' => 3]], 'The title of the report is a text'],
        ];

        foreach ($refused as [$config, $message]) {
            try {
                $this->load($config);
                self::fail(json_encode($config, \JSON_THROW_ON_ERROR));
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString($message, $invalidConfigurationException->getMessage());
            }
        }

        self::assertSame('x'.str_repeat('-', 63), $this->load(['media' => ['collection' => 'x'.str_repeat('-', 63)]], media: true)->getDefinition(MediaLibraryStore::class)->getArgument(3));
        self::assertSame([[str_repeat('é', 255) => StyleRole::Paragraph]], $this->load(['styles' => [str_repeat('é', 255) => 'paragraph']])->getDefinition(StyleMap::class)->getArguments());
        self::assertSame([[], [], 'What was not imported'], \array_slice($this->load(['formats' => ['import' => [], 'export' => []]])->getDefinition(DocumentImports::class)->getArguments(), 3, 3));
        self::assertSame([['withLinkSchemes', ['web+x.y-z'], true]], $this->load(['svg' => ['link_schemes' => ['web+x.y-z']]])->getDefinition(DocumentImportServices::SVG_POLICY)->getMethodCalls());
    }

    public function testWithTheUploadScanBundleEachFileIsScannedFirst(): void
    {
        foreach (['auto', 'on'] as $scan) {
            $container = $this->load(['scan' => $scan], scanner: true);

            self::assertEquals(new Reference(DocumentScreening::class), $container->getDefinition(DocumentImports::class)->getArgument(7));
            self::assertSame(UploadScanScreening::class, (string) $container->getAlias(DocumentScreening::class));
            self::assertEquals([new Reference(UploadScanner::class)], $container->getDefinition(UploadScanScreening::class)->getArguments());
        }

        foreach ([$this->load(['scan' => 'off'], scanner: true), $this->load([])] as $container) {
            self::assertNull($container->getDefinition(DocumentImports::class)->getArgument(7));
            self::assertFalse($container->hasDefinition(UploadScanScreening::class));
            self::assertFalse($container->hasAlias(DocumentScreening::class));
        }
    }

    public function testTheOtherBundlesAreFoundInTheKernelsList(): void
    {
        $container = new ContainerBuilder();
        // Symfony gives each bundle under its name, its class wrapped in an adapter.
        $container->setParameter('kernel.bundles', ['PhilMediaBundle' => 'adapter', 'PhilUploadScanBundle' => 'adapter']);
        $this->extension()->load([[]], $container);

        self::assertSame(MediaLibraryStore::class, (string) $container->getAlias(MediaStore::class));
        self::assertSame(UploadScanScreening::class, (string) $container->getAlias(DocumentScreening::class));

        foreach ([['App\\PhilMediaBundle', 'PhilUploadScanBundle'], 'not a list'] as $bundles) {
            $other = new ContainerBuilder();
            $other->setParameter('kernel.bundles', $bundles);
            $this->extension()->load([[]], $other);

            self::assertSame(MediaFolder::class, (string) $other->getAlias(MediaStore::class));
            self::assertFalse($other->hasAlias(DocumentScreening::class));
        }
    }

    public function testTheScanOnNeedsTheUploadScanBundle(): void
    {
        try {
            $this->load(['scan' => 'on']);
            self::fail('The scan is on without its bundle.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertSame('The scan "on" goes through phil/upload-scan-bundle, which is not registered: register PhilUploadScanBundle, or choose "off".', $invalidConfigurationException->getMessage());
        }
    }

    public function testTheQueueIsRegisteredOnDemand(): void
    {
        $container = $this->load([]);

        self::assertFalse($container->hasDefinition(DocumentImportQueue::class));
        self::assertFalse($container->hasDefinition(ImportDocumentHandler::class));
        self::assertFalse($container->hasAlias(PendingImports::class));

        $container = $this->load(['async' => ['directory' => '/var/imports']]);

        self::assertSame(['/var/imports'], $container->getDefinition(FilesystemPendingImports::class)->getArguments());
        self::assertSame(FilesystemPendingImports::class, (string) $container->getAlias(PendingImports::class));
        self::assertEquals([new Reference(DocumentImports::class), new Reference(PendingImports::class), new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(ImportDocumentHandler::class)->getArguments());
        self::assertSame(['messenger.message_handler' => [[]]], $container->getDefinition(ImportDocumentHandler::class)->getTags());
        self::assertEquals([new Reference(DocumentImports::class), new Reference(PendingImports::class), new Reference(MessageBusInterface::class)], $container->getDefinition(DocumentImportQueue::class)->getArguments());
        self::assertTrue($container->getDefinition(DocumentImportQueue::class)->isPublic());
        self::assertFalse($container->getDefinition(ImportDocumentHandler::class)->isPublic());

        $store = $this->load(['async' => ['enabled' => true, 'store' => 'app.pending_imports']]);

        self::assertSame('app.pending_imports', (string) $store->getAlias(PendingImports::class));
        self::assertFalse($store->hasDefinition(FilesystemPendingImports::class));
        self::assertSame(['%kernel.project_dir%/var/document-imports'], $this->load(['async' => true])->getDefinition(FilesystemPendingImports::class)->getArguments());
    }

    public function testTheTransportOfTheQueueIsChecked(): void
    {
        foreach (['bad name', '', str_repeat('a', 65), 3, "async\n"] as $transport) {
            try {
                $this->load(['async' => ['transport' => $transport]]);
                self::fail(json_encode($transport, \JSON_THROW_ON_ERROR));
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString('The transport is the name of a Messenger transport', $invalidConfigurationException->getMessage());
            }
        }

        foreach (['async', 'a.b-c_D9', str_repeat('a', 64)] as $transport) {
            self::assertTrue($this->load(['async' => ['transport' => $transport]])->hasDefinition(DocumentImportQueue::class));
        }

        try {
            $this->load(['async' => ['directory' => '']]);
            self::fail('An empty directory is taken.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertStringContainsString('cannot contain an empty value', $invalidConfigurationException->getMessage());
        }
    }

    public function testTheMessageIsRoutedToTheConfiguredTransport(): void
    {
        self::assertSame([['messenger' => ['routing' => [ImportDocument::class => 'imports']]]], $this->routing(true, ['async' => ['transport' => 'first']], ['async' => ['transport' => 'imports']], ['async' => ['enabled' => true]], ['async' => 'yes'], ['async' => ['transport' => 3]]));
        self::assertSame([], $this->routing(true, ['async' => ['enabled' => true]]));
        self::assertSame([], $this->routing(false, ['async' => ['transport' => 'imports']]));
    }

    /**
     * The configuration the bundle prepends to FrameworkBundle's, with FrameworkBundle registered or not.
     *
     * @param array<string, mixed> ...$configs
     *
     * @return array<array-key, mixed>
     */
    private function routing(bool $framework, array ...$configs): array
    {
        $container = new ContainerBuilder();
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container->registerExtension($extension);

        if ($framework) {
            $container->registerExtension(new FrameworkStub());
        }

        foreach ($configs as $config) {
            $container->loadFromExtension('phil_document_import', $config);
        }

        $extension->prepend($container);

        return $container->getExtensionConfig('framework');
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config, bool $media = false, bool $scanner = false): ContainerBuilder
    {
        $container = new ContainerBuilder();

        if ($media) {
            $container->registerExtension(new MediaStub());
        }

        if ($scanner) {
            $container->registerExtension(new UploadScanStub());
        }

        $this->extension()->load([$config], $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilDocumentImportBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
