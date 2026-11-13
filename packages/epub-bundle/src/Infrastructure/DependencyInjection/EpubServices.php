<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Infrastructure\DependencyInjection;

use Phil\Epub\EpubWriter;
use Phil\EpubBundle\Application\BookSources;
use Phil\EpubBundle\Application\Epubs;
use Phil\EpubBundle\Application\Message\WriteEpub;
use Phil\EpubBundle\Application\Port\EpubStorage;
use Phil\EpubBundle\Infrastructure\Console\WriteEpubCommand;
use Phil\EpubBundle\Infrastructure\Fonts\EpubFonts;
use Phil\EpubBundle\Infrastructure\Media\MediaAssets;
use Phil\EpubBundle\Infrastructure\Media\MediaLibraryStorage;
use Phil\EpubBundle\Infrastructure\Messenger\EpubHandler;
use Phil\EpubBundle\Infrastructure\Storage\DirectoryStorage;
use Phil\FontsBundle\Application\FontCatalogue;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Domain\Repository\MediaRepository;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The services of the configuration: `Epubs` over the writer, the book sources and the storage — a directory, or the
 * media library —; the command; the handler of `WriteEpub` when Messenger is installed; `EpubFonts` with
 * phil/fonts-bundle, `MediaAssets` with phil/media-bundle.
 */
final class EpubServices
{
    public const string SOURCE_TAG = 'phil_epub.source';

    /**
     * @param array{type: string, directory: string, media_collection: ?string} $storage
     * @param array{obfuscate: bool, directory: string, stylesheet: string}     $fonts
     */
    public static function register(ContainerBuilder $container, ?string $temporaryDirectory, array $storage, array $fonts): void
    {
        $container->register(EpubWriter::class, EpubWriter::class)->setArguments([$temporaryDirectory]);
        $container->register(BookSources::class, BookSources::class)
            ->setArguments([new TaggedIteratorArgument(self::SOURCE_TAG, 'name')]);
        $container->register(DirectoryStorage::class, DirectoryStorage::class)->setArguments([$storage['directory']]);

        if ('media' === $storage['type']) {
            $container->register(MediaLibraryStorage::class, MediaLibraryStorage::class)
                ->setArguments([new Reference(MediaLibrary::class), new Reference(MediaRepository::class), $storage['media_collection'], $temporaryDirectory]);
        }

        $container->setAlias(EpubStorage::class, 'media' === $storage['type'] ? MediaLibraryStorage::class : DirectoryStorage::class)->setPublic(true);
        $container->register(Epubs::class, Epubs::class)
            ->setArguments([new Reference(EpubStorage::class), new Reference(BookSources::class), new Reference(EpubWriter::class)])
            ->setPublic(true);
        $container->register(WriteEpubCommand::class, WriteEpubCommand::class)
            ->setArguments([new Reference(Epubs::class)])
            ->addTag('console.command');

        if (interface_exists(MessageBusInterface::class)) {
            $container->register(EpubHandler::class, EpubHandler::class)
                ->setArguments([new Reference(Epubs::class), new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
                ->addTag('messenger.message_handler', ['handles' => WriteEpub::class]);
        }

        if (class_exists(FontCatalogue::class)) {
            $container->register(EpubFonts::class, EpubFonts::class)
                ->setArguments([new Reference(FontCatalogue::class), $fonts['obfuscate'], $fonts['directory'], $fonts['stylesheet']])
                ->setPublic(true);
        }

        if (class_exists(MediaLibrary::class)) {
            $container->register(MediaAssets::class, MediaAssets::class)
                ->setArguments([new Reference(MediaLibrary::class)])
                ->setPublic(true);
        }
    }
}
