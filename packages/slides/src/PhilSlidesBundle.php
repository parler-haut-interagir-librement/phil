<?php

declare(strict_types=1);

namespace Phil\Slides;

use Override;
use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Level;
use Phil\PagedMediaBundle\Application\PagedMedia;
use Phil\Slides\Application\Contract\PresentedDecks;
use Phil\Slides\Application\DeckReader;
use Phil\Slides\Application\NoPresentedDecks;
use Phil\Slides\Application\SlideRenderer;
use Phil\Slides\Domain\AspectRatio;
use Phil\Slides\Domain\NotesPageSize;
use Phil\Slides\Domain\Slicer;
use Phil\Slides\Domain\SplitMode;
use Phil\Slides\Infrastructure\DependencyInjection\SlidesServicesPass;
use Phil\Slides\Infrastructure\Http\PresenterController;
use Phil\Slides\Infrastructure\Print\DeckPdf;
use Phil\Slides\Infrastructure\Print\DeckPrint;
use Phil\Slides\Infrastructure\Twig\SlidesExtension;
use Phil\Ujo\Ujo;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Slides in a Symfony application: UJO documents, or sources `phil/markup` reads, cut into decks from the
 * configuration; the audience and presenter templates with their Stimulus controllers; each slide rendered by the
 * `Ujo` service of `phil/ujo-bundle`, their buttons the recipe `Button` of the kit the application installed; the print of
 * a deck, as a PDF when `phil/paged-media-bundle` is installed; and the presenter routes of `config/routes.php`, for the
 * role `presenter_role` only, which an application imports when it wants them.
 */
final class PhilSlidesBundle extends AbstractBundle
{
    public const string ASSETS_DIR = __DIR__.'/../assets';

    /** The role the presenter routes ask for when the configuration names none. */
    public const string DEFAULT_PRESENTER_ROLE = 'ROLE_SLIDES_PRESENTER';

    #[Override]
    protected string $extensionAlias = 'phil_slides';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('split')
                    ->info('Where a document is cut: "auto" at its separators when it has one, at its headings otherwise; "separators", "headings" or "both".')
                    ->values(array_map(static fn (SplitMode $mode): string => $mode->value, SplitMode::cases()))
                    ->defaultValue(SplitMode::Auto->value)
                ->end()
                ->integerNode('heading_level')
                    ->info('The deepest heading that starts a slide, 1 to 6.')
                    ->defaultValue(2)
                    ->min(1)
                    ->max(6)
                ->end()
                ->scalarNode('notes_class')
                    ->info("The class of the container blocks that hold the speaker's notes.")
                    ->defaultValue('notes')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('fragment_class')
                    ->info('The class of the container blocks that appear one at a time.')
                    ->defaultValue('fragment')
                    ->validate()
                        ->ifTrue(static fn (mixed $value): bool => !\is_string($value) || 1 !== preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $value))
                        ->thenInvalid('The class of the fragments is 1 to 64 letters, digits, "_" or "-": %s.')
                    ->end()
                ->end()
                ->enumNode('aspect_ratio')
                    ->info('The shape of a slide when the source does not say it: "16:9" or "4:3".')
                    ->values(array_map(static fn (AspectRatio $ratio): string => $ratio->value, AspectRatio::cases()))
                    ->defaultValue(AspectRatio::Wide->value)
                ->end()
                ->integerNode('max_slides')
                    ->info('The most slides a deck holds; a document that makes more is refused.')
                    ->defaultValue(Slicer::DEFAULT_MAX_SLIDES)
                    ->min(1)
                ->end()
                ->integerNode('max_source_bytes')
                    ->info('The largest source read by phil/markup into a deck, in bytes.')
                    ->defaultValue(DeckReader::DEFAULT_MAX_SOURCE_BYTES)
                    ->min(1)
                ->end()
                ->enumNode('markup_level')
                    ->info('The level phil/markup reads a source at: "all" keeps raw HTML as raw blocks and drops HTML comments.')
                    ->values(array_map(static fn (Level $level): string => $level->value, Level::cases()))
                    ->defaultValue(Level::All->value)
                ->end()
                ->scalarNode('presenter_role')
                    ->info('The role the presenter routes of config/routes.php ask for: the presenter view and the PDF with the notes. Everyone else is refused (403).')
                    ->defaultValue(self::DEFAULT_PRESENTER_ROLE)
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('layout')
                    ->info('The Twig template the presenter route extends, with the blocks "title" and "main".')
                    ->defaultValue('@PhilSlides/layout.html.twig')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('kit_subdirectory')
                    ->info('Where the recipes of the kit are, under the components directory; the subdirectory of "ux_toolkit" when null. To set in an environment without the Toolkit.')
                    ->defaultNull()
                ->end()
                ->arrayNode('pdf')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('notes_page_size')
                            ->info('The upright page a slide is printed on with its notes: "letter" or "a4".')
                            ->values(array_map(static fn (NotesPageSize $size): string => $size->value, NotesPageSize::cases()))
                            ->defaultValue(NotesPageSize::Letter->value)
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new SlidesServicesPass());
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('framework') && interface_exists(AssetMapperInterface::class)) {
            $container->extension('framework', ['asset_mapper' => ['paths' => [self::ASSETS_DIR => '@phil/slides']]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{split: string, heading_level: int, notes_class: string, fragment_class: string, aspect_ratio: string, max_slides: int, max_source_bytes: int, markup_level: string, presenter_role: string, layout: string, kit_subdirectory: ?string, pdf: array{notes_page_size: string}} $config */
        $config = $builder->getParameterBag()->resolveValue($config);
        $slicer = [SplitMode::from($config['split']), $config['heading_level'], $config['notes_class'], $config['max_slides'], AspectRatio::from($config['aspect_ratio'])];
        new Slicer(...$slicer);

        $services = $container->services();
        $services->set(Slicer::class)->args($slicer);
        $services->set(SlideRenderer::class)->args([new Reference(Ujo::class)]);
        $services->set(DeckReader::class)->args([
            new Reference(Slicer::class),
            new Reference(Ujo::class),
            new Reference(DefaultCompiler::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            Level::from($config['markup_level']),
            $config['max_source_bytes'],
        ])->public();

        $services->set(SlidesExtension::class)
            // The subdirectory of the Toolkit is read by SlidesServicesPass when the configuration names none.
            ->args([new Reference(DeckReader::class), new Reference(SlideRenderer::class), $config['fragment_class'], $config['kit_subdirectory']])
            ->tag('twig.attribute_extension')
            ->tag('twig.runtime');
        $services->set(DeckPrint::class)
            ->args([new Reference('twig'), NotesPageSize::from($config['pdf']['notes_page_size'])])
            ->public();
        $services->set(DeckPdf::class)
            ->args([new Reference(PagedMedia::class), new Reference(DeckPrint::class)])
            ->public();

        $services->set(PresentedDecks::class, NoPresentedDecks::class);
        $services->set(PresenterController::class)
            ->args([
                new Reference(PresentedDecks::class),
                new Reference('twig'),
                new Reference('router'),
                new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                $config['presenter_role'],
                $config['layout'],
                new Reference(DeckPdf::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->tag('controller.service_arguments')
            ->public();
    }
}
