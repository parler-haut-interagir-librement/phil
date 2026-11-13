<?php

declare(strict_types=1);

namespace Phil\PrintPreflightBundle\Infrastructure\DependencyInjection;

use Phil\FontsBundle\Application\FontCatalogue;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\PrintPreflightBundle\Application\MeasuredDocument;
use Phil\PrintPreflightBundle\Application\Port\FontResolver;
use Phil\PrintPreflightBundle\Application\Preflighter;
use Phil\PrintPreflightBundle\Application\PrintProfiles;
use Phil\PrintPreflightBundle\Infrastructure\Console\PreflightCommand;
use Phil\PrintPreflightBundle\Infrastructure\Fonts\CatalogueFontResolver;
use Phil\PrintPreflightBundle\Infrastructure\Media\MediaImages;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The services of the configuration: the printer profiles, the preflight with the checks of the application, the
 * reader of measured documents, the command, and, when their packages are there, the font resolver of phil/fonts-bundle
 * and the images of phil/media-bundle.
 */
final class PrintPreflightServices
{
    public const string CHECK_TAG = 'phil_print_preflight.check';

    /**
     * @param array<string, array{trim_sizes: list<string>, uniform_trim_size: bool, bleed: string, safety_margin: string, minimum_resolution: float, target_resolution: float, maximum_resolution: ?float, minimum_pages: int, maximum_pages: ?int, page_multiple: int, colour_workflow: string, allow_spot_colours: bool, severities: array<string, string>, ignore: list<string>, maximum_ink_coverage: float, minimum_line_width: string, allow_overprint: bool, allow_transparency: bool}> $profiles
     */
    public static function register(ContainerBuilder $container, array $profiles, ?string $default): void
    {
        $container->register(PrintProfiles::class, PrintProfiles::class)
            ->setFactory([PrintProfiles::class, 'fromConfiguration'])
            ->setArguments([$profiles, $default])
            ->setPublic(true);
        $container->register(Preflighter::class, Preflighter::class)
            ->setArguments([new Reference(PrintProfiles::class), new TaggedIteratorArgument(self::CHECK_TAG)])
            ->setPublic(true);
        $container->register(MeasuredDocument::class, MeasuredDocument::class)
            ->setArguments([new Reference(FontResolver::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ->setPublic(true);
        $container->register(PreflightCommand::class, PreflightCommand::class)
            ->setArguments([new Reference(Preflighter::class), new Reference(MeasuredDocument::class)])
            ->addTag('console.command');

        if (class_exists(FontCatalogue::class)) {
            $container->register(CatalogueFontResolver::class, CatalogueFontResolver::class)
                ->setArguments([new Reference(FontCatalogue::class)]);
            $container->setAlias(FontResolver::class, CatalogueFontResolver::class);
        }

        if (class_exists(Media::class)) {
            $container->register(MediaImages::class, MediaImages::class)->setPublic(true);
        }
    }
}
