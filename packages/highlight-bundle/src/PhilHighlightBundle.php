<?php

declare(strict_types=1);

namespace Phil\HighlightBundle;

use Override;
use Phil\Highlight\Language;
use Phil\HighlightBundle\Infrastructure\DependencyInjection\HighlighterPass;
use Phil\HighlightBundle\Infrastructure\DependencyInjection\HighlightServices;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * `phil/highlight` in a Symfony application: the `Highlighter` service with the application's own languages and
 * aliases, the code blocks of the UJO documents that `phil/ujo-bundle` renders coloured on the server, the `highlight`
 * Twig filter, and a theme stylesheet mapped for AssetMapper.
 */
final class PhilHighlightBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_highlight';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('aliases')
                    ->info('More names for a language, each to the name it stands for: vue: html, jsonc: json.')
                    ->useAttributeAsKey('alias')
                    ->scalarPrototype()
                        ->validate()
                            ->ifTrue(static fn (mixed $name): bool => !HighlightServices::isName($name))
                            ->thenInvalid('A language is named in lower case, by letters, digits and _+#.-, up to 32 characters; %s is not.')
                        ->end()
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $aliases): bool => !array_all(array_keys($aliases), static fn (int|string $alias): bool => HighlightServices::isName((string) $alias)))
                        ->thenInvalid('An alias is written in lower case, by letters, digits and _+#.-, up to 32 characters: %s.')
                    ->end()
                ->end()
                ->arrayNode('ujo')
                    ->info('The code blocks of UJO documents coloured on the server, when phil/ujo-bundle is installed.')
                    ->canBeDisabled()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (interface_exists(AssetMapperInterface::class) && $builder->hasExtension('framework')) {
            $container->extension('framework', ['asset_mapper' => ['paths' => [\dirname(__DIR__).'/assets/styles' => HighlightServices::ASSET_NAMESPACE]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{aliases: array<int|string, string>, ujo: array{enabled: bool}} $config */
        HighlightServices::register($builder, $config['aliases'], $config['ujo']['enabled']);
        $builder->registerForAutoconfiguration(Language::class)->addTag(HighlightServices::LANGUAGE_TAG);
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new HighlighterPass());
    }
}
