<?php

declare(strict_types=1);

namespace Phil\FontsBundle;

use Override;
use Phil\Fonts\Css\FontDisplay;
use Phil\FontsBundle\Infrastructure\DependencyInjection\FontsServices;
use Phil\FontsBundle\Infrastructure\DependencyInjection\RequiredServicesPass;
use Phil\FontsBundle\Infrastructure\Media\MediaFonts;
use Phil\MediaBundle\Application\MediaLibrary;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * `phil/fonts` in a Symfony application, from its configuration:
 *
 * ```yaml
 * phil_fonts:
 *     directory: '%kernel.project_dir%/public/fonts'
 *     public_path: /fonts
 * ```
 *
 * It registers the catalogue of the font directory, read with `ext-brotli` when it is loaded, its `@font-face` rules,
 * its licence register, the commands `phil:fonts:list`, `phil:fonts:licences` and `phil:fonts:css`, and the services of
 * the optional integrations.
 */
final class PhilFontsBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_fonts';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('directory')
                    ->info('The directory of the font files, read with its folders.')
                    ->defaultValue('%kernel.project_dir%/public/fonts')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('public_path')
                    ->info('The URL path the directory is served at, which the @font-face rules point to.')
                    ->defaultValue('/fonts')
                    ->cannotBeEmpty()
                ->end()
                ->enumNode('font_display')
                    ->info('The font-display of the @font-face rules.')
                    ->values(array_map(static fn (FontDisplay $display): string => $display->value, FontDisplay::cases()))
                    ->defaultValue(FontDisplay::Swap->value)
                ->end()
                ->scalarNode('licence_register')
                    ->info('The JSON file that records each font file once its licence is checked.')
                    ->defaultValue('%kernel.project_dir%/config/fonts/licences.json')
                    ->cannotBeEmpty()
                ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{directory: string, public_path: string, font_display: string, licence_register: string} $config */
        FontsServices::register($builder, $config);
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RequiredServicesPass([MediaFonts::class => MediaLibrary::class]));
    }
}
