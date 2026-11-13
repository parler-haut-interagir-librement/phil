<?php

declare(strict_types=1);

namespace Phil\CommentsBundle;

use Override;
use Phil\CommentsBundle\Infrastructure\DependencyInjection\CommentsServices;
use Phil\CommentsBundle\Infrastructure\DependencyInjection\CsrfProtectionPass;
use Phil\CommentsBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\CommentsBundle\Infrastructure\Http\CommentsRequest;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Comments anchored to passages, for an application: the configuration sets where threads are kept and the services
 * of the application behind the endpoints — who comments, what they may do, how authors are named. The tables are
 * mapped for Doctrine, the controller's CSRF token is added to the stateless ones of FrameworkBundle, with AssetMapper
 * the controller and its stylesheet are served without a build step, and with Symfony UX Twig Component the comments
 * on a whole page are drawn by the server, with plain forms.
 */
final class PhilCommentsBundle extends AbstractBundle
{
    public const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    public const string STYLES_DIR = __DIR__.'/../assets/styles';

    #[Override]
    protected string $extensionAlias = 'phil_comments';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('store')
                    ->info('Where threads and comments are kept: "doctrine", or "memory" for tests.')
                    ->values(['doctrine', 'memory'])
                    ->defaultValue('doctrine')
                ->end()
                ->scalarNode('entity_manager')
                    ->info('The Doctrine entity manager of the tables.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
                ->scalarNode('author_provider')
                    ->info('The service, an AuthorProvider, that names who comments; the endpoints need it.')
                    ->defaultNull()
                ->end()
                ->scalarNode('policy')
                    ->info('The service, a CommentPolicy, that says who may read, comment, resolve, decide and moderate; the endpoints need it.')
                    ->defaultNull()
                ->end()
                ->scalarNode('author_directory')
                    ->info('The service, an AuthorDirectory, that names authors; none shows their references.')
                    ->defaultNull()
                ->end()
                ->booleanNode('aria_roles')
                    ->info('Whether comments and suggestions take the roles "comment" and "suggestion" of the WAI-ARIA 1.3 draft, a passage pointing to its comments by aria-details.')
                    ->defaultFalse()
                ->end()
                ->scalarNode('kit_subdirectory')
                    ->info('Where the recipes of the kit are, under the components directory; the subdirectory of "ux_toolkit" when null. To set in an environment without the Toolkit.')
                    ->defaultNull()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine')) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['PhilCommentsBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Phil\CommentsBundle\Domain\Model',
            ]]]]);
        }

        if ($builder->hasExtension('framework')) {
            $container->extension('framework', ['csrf_protection' => ['stateless_token_ids' => [CommentsRequest::TOKEN_ID]]]);
        }

        // AssetMapperBundle has its own extension since Symfony 8.2; `framework.asset_mapper` is only an alias of it.
        if ($builder->hasExtension('asset_mapper')) {
            $container->extension('asset_mapper', ['paths' => [
                self::CONTROLLERS_DIR => '@phil/comments-bundle',
                self::STYLES_DIR => 'phil-comments',
            ]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{store: string, entity_manager: string, author_provider: ?string, policy: ?string, author_directory: ?string, aria_roles: bool, kit_subdirectory: ?string} $config */
        CommentsServices::register($builder, $config);
    }

    /**
     * Stops the container with a helpful message when the application turns the CSRF protection off, and names the
     * recipes of the kit after the Toolkit's subdirectory.
     */
    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new CsrfProtectionPass(CommentsRequest::class, CommentsRequest::TOKEN_ID));
        $container->addCompilerPass(new KitSubdirectoryPass());
    }
}
