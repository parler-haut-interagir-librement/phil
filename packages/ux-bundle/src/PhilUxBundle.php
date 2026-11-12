<?php

declare(strict_types=1);

namespace Phil\UxBundle;

use Composer\InstalledVersions;
use LogicException;
use Override;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Configuration;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * PHIL UX in a Symfony application: the form theme of PHIL UX and the stylesheet of phil/ux-css.
 *
 * - **The form theme** is prepended to `twig.form_themes`, as Symfony adds its own default theme, so every form
 *   renders with it; the themes the application lists come after it and win. `form_theme: false` turns it off.
 * - **The stylesheet is an asset**: with AssetMapper, the `dist/` directory of phil/ux-css is mapped as `phil-ux/`,
 *   and `asset('phil-ux/phil-ux.css')` serves it.
 */
final class PhilUxBundle extends AbstractBundle
{
    public const string FORM_THEME = '@PhilUx/form/phil_ux_layout.html.twig';

    public const string STYLESHEET = 'phil-ux/phil-ux.css';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->booleanNode('form_theme')
                    ->info('Whether every form renders with the PHIL UX form theme; false leaves twig.form_themes to the application.')
                    ->defaultTrue()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('twig') && $this->formThemeEnabled($builder)) {
            $container->extension('twig', ['form_themes' => [self::FORM_THEME]], prepend: true);
        }

        if (interface_exists(AssetMapperInterface::class) && $builder->hasExtension('framework')) {
            $container->extension('framework', ['asset_mapper' => ['paths' => [self::stylesheetDirectory() => 'phil-ux']]]);
        }
    }

    /**
     * The `dist/` directory of phil/ux-css, where Composer installed it.
     */
    public static function stylesheetDirectory(): string
    {
        $package = InstalledVersions::getInstallPath('phil/ux-css') ?? throw new LogicException('phil/ux-css is not installed: require it with Composer.');

        return $package.'/dist';
    }

    /**
     * The bundle's own configuration, processed before the extensions load to decide whether the theme is prepended.
     */
    private function formThemeEnabled(ContainerBuilder $builder): bool
    {
        $config = new Processor()->processConfiguration(new Configuration($this, $builder, $this->extensionAlias), $builder->getExtensionConfig($this->extensionAlias));

        return true === $config['form_theme'];
    }
}
