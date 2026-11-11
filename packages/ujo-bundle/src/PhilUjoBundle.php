<?php

declare(strict_types=1);

namespace Phil\UjoBundle;

use Override;
use Phil\UjoBundle\Infrastructure\DependencyInjection\UjoServices;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * `phil/ujo` in a Symfony application: the `Ujo` service built from the configuration, the `ujo_html` and `ujo_text`
 * filters and functions in Twig, the `UjoType` form field and the `ValidUjo` constraint.
 */
final class PhilUjoBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_ujo';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->booleanNode('allow_raw')
                    ->info('Whether documents may hold raw HTML blocks, sanitized with the raw profile; false refuses them.')
                    ->defaultFalse()
                ->end()
                ->integerNode('max_bytes')
                    ->info('The largest UJO JSON read, in bytes.')
                    ->defaultValue(UjoServices::MAX_BYTES)
                    ->min(1)
                ->end()
                ->integerNode('max_depth')
                    ->info('The deepest nesting of blocks inside blocks.')
                    ->defaultValue(UjoServices::MAX_DEPTH)
                    ->min(1)
                ->end()
                ->integerNode('max_blocks')
                    ->info('The most blocks a document may hold, nested ones included.')
                    ->defaultValue(UjoServices::MAX_BLOCKS)
                    ->min(1)
                ->end()
                ->integerNode('max_html_length')
                    ->info('The longest HTML field, in characters.')
                    ->defaultValue(UjoServices::MAX_HTML_LENGTH)
                    ->min(1)
                ->end()
                ->integerNode('max_raw_length')
                    ->info('The longest HTML of a raw block, in characters.')
                    ->defaultValue(UjoServices::MAX_RAW_LENGTH)
                    ->min(1)
                ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{allow_raw: bool, max_bytes: int, max_depth: int, max_blocks: int, max_html_length: int, max_raw_length: int} $config */
        UjoServices::register($builder, $config['allow_raw'], $config['max_bytes'], $config['max_depth'], $config['max_blocks'], $config['max_html_length'], $config['max_raw_length']);
    }
}
