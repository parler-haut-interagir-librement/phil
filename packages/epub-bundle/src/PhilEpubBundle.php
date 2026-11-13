<?php

declare(strict_types=1);

namespace Phil\EpubBundle;

use Override;
use Phil\EpubBundle\Application\AsBookSource;
use Phil\EpubBundle\Infrastructure\DependencyInjection\EpubServices;
use Phil\EpubBundle\Infrastructure\DependencyInjection\RequiredServicesPass;
use Phil\EpubBundle\Infrastructure\Fonts\EpubFonts;
use Phil\EpubBundle\Infrastructure\Media\MediaAssets;
use Phil\FontsBundle\Application\FontCatalogue;
use Phil\MediaBundle\Application\MediaLibrary;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * `phil/epub` in a Symfony application, from its configuration:
 *
 * ```yaml
 * phil_epub:
 *     storage: { type: media, media_collection: books }
 *     fonts: { obfuscate: true }
 * ```
 *
 * It registers `Epubs`, the book sources marked `#[AsBookSource]`, the storage, the command `phil:epub:write`, the
 * handler of `WriteEpub`, and the services of the optional integrations.
 */
final class PhilEpubBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_epub';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('temporary_directory')
                    ->info("Where a book is written before it is read back; the system's temporary directory when null.")
                    ->defaultNull()
                ->end()
                ->arrayNode('storage')
                    ->info('Where the books written by messages and by the command are kept; alias Phil\EpubBundle\Application\Port\EpubStorage to keep them elsewhere.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('type')->values(['directory', 'media'])->defaultValue('directory')->end()
                        ->scalarNode('directory')->info('directory: where the books are kept.')->defaultValue('%kernel.project_dir%/var/epub')->cannotBeEmpty()->end()
                        ->scalarNode('media_collection')->info('media: the key of the collection the books go to; none when null.')->defaultNull()->end()
                    ->end()
                ->end()
                ->arrayNode('fonts')
                    ->info('How EpubFonts embeds the fonts of phil/fonts-bundle.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('obfuscate')->info('Whether embedded fonts are obfuscated, as many font licences for e-books ask.')->defaultTrue()->end()
                        ->scalarNode('directory')->info('The folder of the fonts in the book.')->defaultValue('fonts')->cannotBeEmpty()->end()
                        ->scalarNode('stylesheet')->info('The path of the stylesheet of their @font-face rules in the book.')->defaultValue('css/fonts.css')->cannotBeEmpty()->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{temporary_directory: ?string, storage: array{type: string, directory: string, media_collection: ?string}, fonts: array{obfuscate: bool, directory: string, stylesheet: string}} $config */
        EpubServices::register($builder, $config['temporary_directory'], $config['storage'], $config['fonts']);
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->registerAttributeForAutoconfiguration(AsBookSource::class, static function (ChildDefinition $definition, AsBookSource $attribute): void {
            $definition->addTag(EpubServices::SOURCE_TAG, ['name' => $attribute->name]);
        });
        $container->addCompilerPass(new RequiredServicesPass([EpubFonts::class => FontCatalogue::class, MediaAssets::class => MediaLibrary::class]));
    }
}
