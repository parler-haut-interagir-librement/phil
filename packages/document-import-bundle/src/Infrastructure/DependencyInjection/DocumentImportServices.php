<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Infrastructure\DependencyInjection;

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
use Phil\DocumentImportBundle\Infrastructure\Claim\FilesystemPendingImports;
use Phil\DocumentImportBundle\Infrastructure\Media\MediaLibraryStore;
use Phil\DocumentImportBundle\Infrastructure\Twig\ImportReportExtension;
use Phil\DocumentImportBundle\Infrastructure\UploadScan\UploadScanScreening;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Domain\Repository\MediaRepository;
use Phil\MediaBundle\Infrastructure\Twig\MediaExtension;
use Phil\SvgSanitizer\Policy;
use Phil\SvgSanitizer\SvgSanitizer;
use Phil\UploadScanBundle\Application\UploadScanner;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The services of the configuration: the `DocumentImporter`, its `Limits` and `StyleMap`, the `SvgSanitizer` of its
 * images and their policy, the `MediaStore` the images go to — `MediaFolder`, or `MediaLibraryStore` and the address
 * of a medium when the images are kept in `phil/media-bundle` —, the `DocumentExporter`, `DocumentImports` over
 * both and the screening of its files, the queue of the imports on a worker, and the title of the report in Twig.
 */
final class DocumentImportServices
{
    /** The policy of the SVG sanitizer. */
    public const string SVG_POLICY = 'phil_document_import.svg_policy';

    /** The closure from a medium of `phil/media-bundle` to the address an image block shows. */
    public const string MEDIA_ADDRESS = 'phil_document_import.media_address';

    /**
     * @param array{store: string, prefix: string, collection: ?string}                                                                 $media
     * @param array{entry_bytes: int, total_bytes: int, depth: int, objects: int}                                                       $limits
     * @param array{max_bytes: int, max_depth: int, max_elements: int, data_images: bool, animations: bool, link_schemes: list<string>} $svg
     * @param bool                                                                                                                      $library whether the images are kept in `phil/media-bundle`
     */
    public static function register(ContainerBuilder $container, array $media, array $limits, array $svg, bool $library): void
    {
        $policy = $container->register(self::SVG_POLICY, Policy::class)
            ->setArguments([null, $svg['data_images'], $svg['max_bytes'], $svg['max_depth'], $svg['max_elements']])
            ->addMethodCall('withLinkSchemes', $svg['link_schemes'], true);

        if ($svg['animations']) {
            $policy->addMethodCall('withAnimations', [], true);
        }

        $container->register(SvgSanitizer::class, SvgSanitizer::class)
            ->setArguments([new Reference(self::SVG_POLICY)])
            ->setPublic(true);
        $container->register(Limits::class, Limits::class)
            ->setArguments([$limits['entry_bytes'], $limits['total_bytes'], $limits['depth'], $limits['objects']]);
        $container->register(MediaFolder::class, MediaFolder::class)
            ->setArguments([$media['prefix']]);

        if ($library) {
            $container->register(self::MEDIA_ADDRESS, Closure::class)
                ->setFactory([Closure::class, 'fromCallable'])
                ->setArguments([[new Reference(MediaExtension::class), 'url']]);
            $container->register(MediaLibraryStore::class, MediaLibraryStore::class)
                ->setArguments([new Reference(MediaLibrary::class), new Reference(MediaRepository::class), new Reference(self::MEDIA_ADDRESS), $media['collection']]);
        }

        $container->setAlias(MediaStore::class, $library ? MediaLibraryStore::class : MediaFolder::class);
        $container->register(DocumentImporter::class, DocumentImporter::class)
            ->setArguments([new Reference(MediaStore::class), null, null, null, new Reference(Limits::class), new Reference(SvgSanitizer::class), new Reference(StyleMap::class)])
            ->setPublic(true);
    }

    /**
     * The `StyleMap`, the `DocumentExporter` and `DocumentImports`, after `register()`.
     *
     * @param array{import: list<string>, export: list<string>}          $formats the formats read and written
     * @param array<string, string>                                      $styles  the role of each style, by its name
     * @param array{media_source: ?string, temporary_directory: ?string} $export  the service the exporter reads images from, and its directory
     * @param bool                                                       $scan    whether each file goes through the scanner of `phil/upload-scan-bundle`
     */
    public static function registerImports(ContainerBuilder $container, array $formats, array $styles, string $reportTitle, array $export, bool $scan): void
    {
        $roles = [];

        foreach ($styles as $style => $role) {
            $roles[$style] = StyleRole::from($role);
        }

        $container->register(StyleMap::class, StyleMap::class)
            ->setArguments([$roles]);
        $container->register(DocumentExporter::class, DocumentExporter::class)
            ->setArguments([
                null === $export['media_source'] ? null : new Reference($export['media_source']),
                new Reference(SvgSanitizer::class),
                $export['temporary_directory'],
            ])
            ->setPublic(true);
        $container->register(DocumentImports::class, DocumentImports::class)
            ->setArguments([
                new Reference(DocumentImporter::class),
                new Reference(DocumentExporter::class),
                new Reference(Limits::class),
                array_map(Format::from(...), $formats['import']),
                array_map(Format::from(...), $formats['export']),
                $reportTitle,
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                $scan ? new Reference(DocumentScreening::class) : null,
            ])
            ->setPublic(true);

        if ($scan) {
            $container->register(UploadScanScreening::class, UploadScanScreening::class)
                ->setArguments([new Reference(UploadScanner::class)]);
            $container->setAlias(DocumentScreening::class, UploadScanScreening::class);
        }

        $container->register(ImportReportExtension::class, ImportReportExtension::class)
            ->setArguments([$reportTitle])
            ->addTag('twig.extension');
    }

    /**
     * The imports on a worker, after `registerImports()`: the files kept in a directory, or in the service `store`
     * names, `ImportDocument` handled, and `DocumentImportQueue` dispatching it on the application's bus.
     *
     * @param array{enabled: bool, transport: ?string, directory: string, store: ?string} $async the transport is routed by the bundle
     */
    public static function registerQueue(ContainerBuilder $container, array $async): void
    {
        if (null === $async['store']) {
            $container->register(FilesystemPendingImports::class, FilesystemPendingImports::class)
                ->setArguments([$async['directory']]);
        }

        $container->setAlias(PendingImports::class, $async['store'] ?? FilesystemPendingImports::class);
        $container->register(ImportDocumentHandler::class, ImportDocumentHandler::class)
            ->setArguments([
                new Reference(DocumentImports::class),
                new Reference(PendingImports::class),
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('messenger.message_handler');
        $container->register(DocumentImportQueue::class, DocumentImportQueue::class)
            ->setArguments([new Reference(DocumentImports::class), new Reference(PendingImports::class), new Reference(MessageBusInterface::class)])
            ->setPublic(true);
    }
}
