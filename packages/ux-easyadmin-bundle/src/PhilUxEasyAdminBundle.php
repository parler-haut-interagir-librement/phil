<?php

declare(strict_types=1);

namespace Phil\UxEasyAdminBundle;

use Override;
use Phil\UxEasyAdminBundle\Infrastructure\EasyAdmin\ThemeStylesheet;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * PHIL UX in the EasyAdmin back-office: EasyAdmin's variables given the colours, corners and fonts of phil/ux-css.
 *
 * - **The stylesheet is an asset**: the bundle maps its `assets/styles` as `phil-ux-easyadmin/` in AssetMapper. The
 *   file holds the tokens of phil/ux-css and the theme, each in its cascade layer (`ThemeSheet`).
 * - **Every page of every dashboard links it**: `ThemeStylesheet` adds it to the assets of the request's EasyAdmin
 *   context. `theme: false` leaves it to the dashboards.
 */
final class PhilUxEasyAdminBundle extends AbstractBundle
{
    public const string STYLESHEET = 'phil-ux-easyadmin/phil-ux-easyadmin.css';

    public const string STYLES_DIR = __DIR__.'/../assets/styles';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->booleanNode('theme')
                    ->info('Whether every page of every dashboard links the PHIL UX theme; false leaves it to configureAssets().')
                    ->defaultTrue()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('framework')) {
            $container->extension('framework', ['asset_mapper' => ['paths' => [self::STYLES_DIR => 'phil-ux-easyadmin']]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (true === $config['theme']) {
            $container->services()
                ->set('phil_ux_easy_admin.theme_stylesheet', ThemeStylesheet::class)
                ->args([self::STYLESHEET])
                // After EasyAdmin's router listener (priority 1), which puts the context in the request.
                ->tag('kernel.event_listener', ['event' => RequestEvent::class, 'priority' => -8]);
        }
    }
}
