<?php

declare(strict_types=1);

namespace Phil\FontsBundle\Infrastructure\DependencyInjection;

use Phil\Fonts\Css\FontDisplay;
use Phil\FontsBundle\Application\FontCatalogue;
use Phil\FontsBundle\Application\FontFaces;
use Phil\FontsBundle\Application\FontFiles;
use Phil\FontsBundle\Application\Licences;
use Phil\FontsBundle\Infrastructure\Console\CheckLicencesCommand;
use Phil\FontsBundle\Infrastructure\Console\FontFacesCommand;
use Phil\FontsBundle\Infrastructure\Console\ListFontsCommand;
use Phil\FontsBundle\Infrastructure\Media\MediaFonts;
use Phil\FontsBundle\Infrastructure\PagedMedia\PdfFonts;
use Phil\FontsBundle\Infrastructure\Twig\FontsExtension;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\PagedMedia\Font\TrueTypeFont;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Attribute\AsTwigFunction;

/**
 * The services of the configuration: the reader, with `ext-brotli` when it is loaded; the catalogue of the directory;
 * the `@font-face` rules; the licence register; the three commands; and, when their packages are installed, the Twig
 * function, the fonts of the native PDF writer and the fonts of the media library.
 */
final class FontsServices
{
    /**
     * @param array{directory: string, public_path: string, font_display: string, licence_register: string} $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $container->register(FontFiles::class, FontFiles::class)
            ->setFactory([FontFiles::class, 'withAvailableBrotli'])
            ->setPublic(true);
        $container->register(FontCatalogue::class, FontCatalogue::class)
            ->setArguments([$config['directory'], new Reference(FontFiles::class)])
            ->setPublic(true);
        $container->register(FontFaces::class, FontFaces::class)
            ->setArguments([new Reference(FontCatalogue::class), $config['public_path'], FontDisplay::from($config['font_display'])])
            ->setPublic(true);
        $container->register(Licences::class, Licences::class)
            ->setArguments([new Reference(FontCatalogue::class), $config['licence_register']])
            ->setPublic(true);

        $container->register(ListFontsCommand::class, ListFontsCommand::class)
            ->setArguments([new Reference(FontCatalogue::class)])
            ->addTag('console.command');
        $container->register(CheckLicencesCommand::class, CheckLicencesCommand::class)
            ->setArguments([new Reference(Licences::class)])
            ->addTag('console.command');
        $container->register(FontFacesCommand::class, FontFacesCommand::class)
            ->setArguments([new Reference(FontFaces::class)])
            ->addTag('console.command');

        if (class_exists(AsTwigFunction::class)) {
            $container->register(FontsExtension::class, FontsExtension::class)
                ->setArguments([new Reference(FontFaces::class)])
                ->addTag('twig.attribute_extension')
                ->addTag('twig.runtime');
        }

        if (class_exists(TrueTypeFont::class)) {
            $container->register(PdfFonts::class, PdfFonts::class)
                ->setArguments([new Reference(FontCatalogue::class)])
                ->setPublic(true);
        }

        if (class_exists(MediaLibrary::class)) {
            $container->register(MediaFonts::class, MediaFonts::class)
                ->setArguments([new Reference(MediaLibrary::class), new Reference(FontFiles::class)])
                ->setPublic(true);
        }
    }
}
