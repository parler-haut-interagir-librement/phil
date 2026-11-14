<?php

declare(strict_types=1);

namespace Phil\DataTableBundle;

use Override;
use Phil\DataTableBundle\Application\Contract\DataTable;
use Phil\DataTableBundle\Application\Contract\TableAccess;
use Phil\DataTableBundle\Application\Contract\TablePreferences;
use Phil\DataTableBundle\Application\DenyingTableAccess;
use Phil\DataTableBundle\Application\EditValues;
use Phil\DataTableBundle\Application\Export\ExportRows;
use Phil\DataTableBundle\Application\NoTablePreferences;
use Phil\DataTableBundle\Application\RowValues;
use Phil\DataTableBundle\Application\State\StateResolver;
use Phil\DataTableBundle\Application\TableOperations;
use Phil\DataTableBundle\Application\Tables;
use Phil\DataTableBundle\Application\View\TableViews;
use Phil\DataTableBundle\Infrastructure\DependencyInjection\CsrfProtectionPass;
use Phil\DataTableBundle\Infrastructure\DependencyInjection\DataTablesPass;
use Phil\DataTableBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\DataTableBundle\Infrastructure\Export\TableExports;
use Phil\DataTableBundle\Infrastructure\Http\TableController;
use Phil\DataTableBundle\Infrastructure\Http\TableExportController;
use Phil\DataTableBundle\Infrastructure\Http\TableGuard;
use Phil\DataTableBundle\Infrastructure\Security\AuthorizationCheckerAccess;
use Phil\DataTableBundle\Infrastructure\Settings\SettingsTablePreferences;
use Phil\DataTableBundle\Infrastructure\Twig\CellBlocks;
use Phil\DataTableBundle\Infrastructure\Twig\Components\TableComponent;
use Phil\DataTableBundle\Infrastructure\Twig\DataTableExtension;
use Phil\DataTableBundle\Infrastructure\Twig\KitComponents;
use Phil\DataTableExport\ExportWriters;
use Phil\SettingsBundle\Application\Contract\CurrentUser;
use Phil\SettingsBundle\Application\Settings;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * Data tables as a Live Component: the tables of the application found by their name, the checks of their changes,
 * the routes of a browser without JavaScript, and, when their packages are there, Symfony Security, the settings of
 * each user, the export of phil/data-table-export and AssetMapper.
 */
final class PhilDataTableBundle extends AbstractBundle
{
    public const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    public const string STYLES_DIR = __DIR__.'/../assets/styles';

    private const string ACCESSOR = 'phil_data_table.property_accessor';

    #[Override]
    protected string $extensionAlias = 'phil_data_table';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new CsrfProtectionPass());
        $container->addCompilerPass(new DataTablesPass());
        $container->addCompilerPass(new KitSubdirectoryPass());
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('access')
                    ->info('The TableAccess service answering the voter attributes; Symfony Security when it is there, and refusing them all otherwise.')
                    ->defaultNull()
                ->end()
                ->scalarNode('preferences')
                    ->info('The TablePreferences service keeping what a user chose; phil/settings-bundle when it is there, and nothing otherwise.')
                    ->defaultNull()
                ->end()
                ->arrayNode('cell_themes')
                    ->info("The templates whose blocks draw the cells of Column::block() before the column's own template, the first one over the others.")
                    ->scalarPrototype()->cannotBeEmpty()->end()
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
        if ($builder->hasExtension('framework')) {
            $container->extension('framework', ['csrf_protection' => ['stateless_token_ids' => [TableGuard::TOKEN_ID]]]);

            if (interface_exists(AssetMapperInterface::class)) {
                $container->extension('framework', ['asset_mapper' => ['paths' => [
                    self::CONTROLLERS_DIR => '@phil/data-table-bundle',
                    self::STYLES_DIR => 'phil-data-table',
                ]]]);
            }
        }

        if ($builder->hasExtension('phil_settings')) {
            $container->extension('phil_settings', ['settings' => [SettingsTablePreferences::KEY => [
                'type' => 'text',
                'default' => '{}',
                'section' => 'general',
                'max_length' => SettingsTablePreferences::MAX_LENGTH,
                'translation_domain' => TableOperations::DOMAIN,
            ]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{access: ?string, preferences: ?string, cell_themes: list<string>, kit_subdirectory: ?string} $config */
        $builder->registerForAutoconfiguration(DataTable::class)->addTag(DataTablesPass::TAG);
        $services = $container->services();

        $services->set(self::ACCESSOR, PropertyAccessor::class)->factory(PropertyAccess::class.'::createPropertyAccessor');
        $services->set(RowValues::class)->args([new Reference(self::ACCESSOR)]);
        $services->set(EditValues::class)->args([new Reference(RowValues::class)]);
        $services->set(StateResolver::class);
        $services->set(DenyingTableAccess::class);
        $services->set(NoTablePreferences::class);

        $builder->setAlias(TableAccess::class, $config['access'] ?? $this->defaultAccess($builder));
        $builder->setAlias(TablePreferences::class, $config['preferences'] ?? $this->defaultPreferences($builder));

        $services->set(Tables::class)->args([new AbstractArgument('The tables by name, given by DataTablesPass.'), new Reference(TableAccess::class)])->public();
        $services->set(TableOperations::class)->args([new Reference(TableAccess::class), new Reference(RowValues::class), new Reference(EditValues::class), new Reference('validator'), new Reference('translator')]);
        $services->set(TableViews::class)->args([new Reference(StateResolver::class), new Reference(TableAccess::class), new Reference(RowValues::class), new Reference(EditValues::class), new Reference(TableOperations::class), new Reference('translator')]);
        $services->set(TableGuard::class)->args([new Reference(CsrfProtectionPass::TOKEN_MANAGER), '%kernel.secret%']);
        $services->set(TableController::class)->args([new Reference(Tables::class), new Reference(TableOperations::class), new Reference(TableGuard::class), new Reference('twig'), new Reference('translator')])->public();
        // The subdirectory of the Toolkit is read by KitSubdirectoryPass when the configuration names none.
        $services->set(KitComponents::class)->args([$config['kit_subdirectory']]);
        $services->set(CellBlocks::class)->args([$config['cell_themes']])->tag('kernel.reset', ['method' => 'reset']);
        $services->set(DataTableExtension::class)->args([new Reference(KitComponents::class), new Reference(CellBlocks::class)])->tag('twig.attribute_extension')->tag('twig.runtime');
        $services->set(ExportRows::class)->args([new Reference(StateResolver::class), new Reference(RowValues::class), new Reference('translator')]);
        $this->exports($builder);
        $services->set(TableComponent::class)
            ->args([new Reference(Tables::class), new Reference(TableViews::class), new Reference(TableOperations::class), new Reference(TableGuard::class), new Reference(TablePreferences::class), new Reference('request_stack'), new Reference('translator'), new Reference(TableExports::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ->autoconfigure();
    }

    /**
     * The export of the tables that offer it, with phil/data-table-export.
     */
    private function exports(ContainerBuilder $builder): void
    {
        if (!class_exists(ExportWriters::class)) {
            return;
        }

        $builder->register(ExportWriters::class, ExportWriters::class)->setFactory([ExportWriters::class, 'defaults']);
        $builder->register(TableExports::class, TableExports::class)->setArguments([new Reference(ExportWriters::class), new Reference('router'), new Reference(TableAccess::class)]);
        $builder->register(TableExportController::class, TableExportController::class)
            ->setArguments([new Reference(Tables::class), new Reference(TableGuard::class), new Reference(ExportRows::class), new Reference(TableExports::class), new Reference('twig'), new Reference('translator')])
            ->setPublic(true);
    }

    private function defaultAccess(ContainerBuilder $builder): string
    {
        if (!$builder->hasExtension('security')) {
            return DenyingTableAccess::class;
        }

        $builder->register(AuthorizationCheckerAccess::class)->setArguments([new Reference('security.authorization_checker')]);

        return AuthorizationCheckerAccess::class;
    }

    private function defaultPreferences(ContainerBuilder $builder): string
    {
        if (!$builder->hasExtension('phil_settings')) {
            return NoTablePreferences::class;
        }

        $builder->register(SettingsTablePreferences::class)->setArguments([new Reference(Settings::class), new Reference(CurrentUser::class), new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);

        return SettingsTablePreferences::class;
    }
}
