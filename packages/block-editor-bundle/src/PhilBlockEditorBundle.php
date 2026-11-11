<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle;

use Override;
use Phil\BlockEditorBundle\Application\Toolset;
use Phil\BlockEditorBundle\Infrastructure\DependencyInjection\BlockEditorServices;
use Phil\BlockEditorBundle\Infrastructure\DependencyInjection\CsrfProtectionPass;
use Phil\BlockEditorBundle\Infrastructure\Http\EditorRequest;
use Phil\BlockEditorBundle\Infrastructure\Http\ImageUploadController;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * A block editor for Symfony forms. The configuration sets the toolset every field starts from, the colour tokens,
 * the services behind image uploads and the `@` and `[[` menus, and the attribute their callers must be granted. The form theme is added to Twig's, the editor's
 * CSRF token to the stateless ones of FrameworkBundle, and with AssetMapper the controller and its stylesheet are
 * served without a build step.
 */
final class PhilBlockEditorBundle extends AbstractBundle
{
    public const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    public const string STYLES_DIR = __DIR__.'/../assets/styles';

    public const string FORM_THEME = '@PhilBlockEditor/form/block_editor.html.twig';

    public const array COLORS = ['accent', 'muted', 'success', 'warning', 'danger'];

    #[Override]
    protected string $extensionAlias = 'phil_block_editor';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('preset')
                    ->info('The tools every field starts from: "full", or "reduced" for simple sites.')
                    ->values(['full', 'reduced'])
                    ->defaultValue('full')
                ->end()
                ->arrayNode('tools')
                    ->info('The block types offered, replacing those of the preset.')
                    ->scalarPrototype()->end()
                    ->defaultNull()
                ->end()
                ->arrayNode('inline_tools')
                    ->info('The inline tools offered, replacing those of the preset.')
                    ->scalarPrototype()->end()
                    ->defaultNull()
                ->end()
                ->arrayNode('colors')
                    ->info("The colour tokens of the text colour tool, as the site's stylesheet names them.")
                    ->scalarPrototype()->end()
                    ->defaultValue(self::COLORS)
                ->end()
                ->scalarNode('image_uploader')
                    ->info('The service, an ImageUploader, that keeps uploaded images; none takes image addresses only.')
                    ->defaultNull()
                ->end()
                ->scalarNode('mention_search')
                    ->info('The service, a ReferenceSearch, behind the "@" menu; none leaves "@" as text.')
                    ->defaultNull()
                ->end()
                ->scalarNode('link_search')
                    ->info('The service, a ReferenceSearch, behind the "[[" menu; none leaves "[[" as text.')
                    ->defaultNull()
                ->end()
                ->integerNode('max_upload_bytes')
                    ->info('The largest image accepted, in bytes.')
                    ->defaultValue(ImageUploadController::MAX_BYTES)
                    ->min(1)
                ->end()
                ->scalarNode('access')
                    ->info("The attribute the application's security must grant to call the upload and search endpoints; null leaves them to its own access_control rules.")
                    ->defaultValue(EditorRequest::ACCESS)
                ->end()
            ->end()
            ->validate()
                ->always(static function (array $config): array {
                    /** @var array{preset: string, tools: ?list<string>, inline_tools: ?list<string>} $config */
                    Toolset::preset($config['preset'], $config['tools'], $config['inline_tools']);

                    return $config;
                })
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('twig')) {
            $container->extension('twig', ['form_themes' => [self::FORM_THEME]]);
        }

        if ($builder->hasExtension('framework')) {
            $container->extension('framework', ['csrf_protection' => ['stateless_token_ids' => [EditorRequest::TOKEN_ID]]]);
        }

        if (interface_exists(AssetMapperInterface::class) && $builder->hasExtension('framework')) {
            $container->extension('framework', ['asset_mapper' => ['paths' => [
                self::CONTROLLERS_DIR => '@phil/block-editor-bundle',
                self::STYLES_DIR => 'phil-block-editor',
            ]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{preset: string, tools: ?list<string>, inline_tools: ?list<string>, colors: list<string>, image_uploader: ?string, mention_search: ?string, link_search: ?string, max_upload_bytes: int, access: ?string} $config */
        BlockEditorServices::register($builder, $config);
    }

    /**
     * Stops the container with a helpful message when the application turns the CSRF protection off.
     */
    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new CsrfProtectionPass(EditorRequest::class, EditorRequest::TOKEN_ID));
    }
}
