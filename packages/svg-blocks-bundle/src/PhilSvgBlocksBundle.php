<?php

declare(strict_types=1);

namespace Phil\SvgBlocksBundle;

use Override;
use Phil\QrCode\ErrorCorrection;
use Phil\SvgBlocksBundle\Infrastructure\DependencyInjection\StoredMarkupPass;
use Phil\SvgBlocksBundle\Infrastructure\DependencyInjection\SvgBlocksServices;
use Phil\SvgBlocksBundle\Infrastructure\Http\PreviewController;
use Phil\SvgBlocksBundle\Infrastructure\Http\PreviewRequest;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * `phil/svg-blocks` in a Symfony application: the math, mermaid, chart and qrCode blocks of every document a UJO
 * field receives drawn once and kept, signed, with the block; the documents `phil/ujo-bundle` renders drawn from that
 * markup; a preview endpoint and its Stimulus controller for the author; and the diagrams drawn in the background
 * through the Mermaid service.
 *
 * ```yaml
 * phil_svg_blocks:
 *     mermaid:
 *         url: 'http://mermaid:3000'
 *         documents: App\Content\ArticleDocuments
 * ```
 */
final class PhilSvgBlocksBundle extends AbstractBundle
{
    public const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    #[Override]
    protected string $extensionAlias = 'phil_svg_blocks';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('secret')
                    ->info('The secret the drawings are signed with, at least 32 bytes; the kernel secret by default.')
                    ->defaultValue('%kernel.secret%')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('qr_code')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('error_correction')
                            ->info('The error correction of the QR codes: L, M, Q or H.')
                            ->values(array_map(static fn (ErrorCorrection $level): string => $level->value, ErrorCorrection::cases()))
                            ->defaultValue(ErrorCorrection::Medium->value)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('chart')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('width')->info('The width of a chart, in pixels.')->defaultValue(640)->min(100)->max(4000)->end()
                        ->integerNode('height')->info('The height of a chart, in pixels.')->defaultValue(400)->min(100)->max(4000)->end()
                    ->end()
                ->end()
                ->arrayNode('math')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('max_length')->info('The longest formula converted, in bytes.')->defaultValue(20_000)->min(1)->end()
                        ->integerNode('max_depth')->info('The deepest nesting of a formula, its macros included.')->defaultValue(64)->min(1)->end()
                        ->integerNode('max_expansions')->info('The most uses of macros a formula expands.')->defaultValue(1_000)->min(1)->end()
                    ->end()
                ->end()
                ->arrayNode('mermaid')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('url')
                            ->info('The address of the Mermaid service, such as http://mermaid:3000; none leaves diagrams to their code.')
                            ->defaultNull()
                        ->end()
                        ->floatNode('timeout')->info('Seconds before a call to the service is given up.')->defaultValue(10.0)->min(0.1)->end()
                        ->booleanNode('async')
                            ->info('Whether diagrams are drawn in the background after a save, rather than during it.')
                            ->defaultTrue()
                        ->end()
                        ->scalarNode('documents')
                            ->info('The service, a MarkupDocuments, that the background pass reads and saves documents with.')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('preview')
                    ->addDefaultsIfNotSet()
                    ->canBeDisabled()
                    ->children()
                        ->integerNode('max_bytes')->info('The largest block previewed, in bytes.')->defaultValue(PreviewController::MAX_BYTES)->min(1)->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('framework')) {
            $container->extension('framework', ['csrf_protection' => ['stateless_token_ids' => [PreviewRequest::TOKEN_ID]]]);
        }

        // AssetMapperBundle has its own extension since Symfony 8.2; `framework.asset_mapper` is only an alias of it.
        if ($builder->hasExtension('asset_mapper')) {
            $container->extension('asset_mapper', ['paths' => [self::CONTROLLERS_DIR => '@phil/svg-blocks-bundle']]);
        }
    }

    /**
     * @param array<array-key, mixed> $config the configuration, processed by the tree above
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{secret: string, qr_code: array{error_correction: string}, chart: array{width: int, height: int}, math: array{max_length: int, max_depth: int, max_expansions: int}, mermaid: array{url: ?string, timeout: float, async: bool, documents: ?string}, preview: array{enabled: bool, max_bytes: int}} $config */
        SvgBlocksServices::register($builder, $config);
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        // Priority 1: before the MessengerPass of symfony/messenger (priority 0, from a bundle registered before this one), which wires the handler this pass may remove.
        $container->addCompilerPass(new StoredMarkupPass(), priority: 1);
    }
}
