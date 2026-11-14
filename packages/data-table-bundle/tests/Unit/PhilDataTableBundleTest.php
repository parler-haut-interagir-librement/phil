<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Unit;

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
use Phil\DataTableBundle\PhilDataTableBundle;
use Phil\DataTableExport\ExportWriters;
use Phil\SettingsBundle\Application\Contract\CurrentUser;
use Phil\SettingsBundle\Application\Settings;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\PropertyAccess\PropertyAccess;

#[CoversClass(PhilDataTableBundle::class)]
final class PhilDataTableBundleTest extends TestCase
{
    public function testWithoutAnythingEveryAttributeIsRefusedAndNothingIsKept(): void
    {
        $container = $this->load([]);

        self::assertSame(DenyingTableAccess::class, (string) $container->getAlias(TableAccess::class));
        self::assertSame(NoTablePreferences::class, (string) $container->getAlias(TablePreferences::class));
        self::assertFalse($container->hasDefinition(AuthorizationCheckerAccess::class));
        self::assertFalse($container->hasDefinition(SettingsTablePreferences::class));
        self::assertTrue($container->getAutoconfiguredInstanceof()[DataTable::class]->hasTag(DataTablesPass::TAG));
        self::assertTrue($container->getDefinition(Tables::class)->isPublic());
        self::assertTrue($container->getDefinition(TableController::class)->isPublic());
    }

    public function testTheServicesAreWired(): void
    {
        $container = $this->load([]);
        $names = [
            RowValues::class => ['phil_data_table.property_accessor'],
            EditValues::class => [RowValues::class],
            TableOperations::class => [TableAccess::class, RowValues::class, EditValues::class, 'validator', 'translator'],
            TableViews::class => [StateResolver::class, TableAccess::class, RowValues::class, EditValues::class, TableOperations::class, 'translator'],
            TableGuard::class => ['security.csrf.token_manager', '%kernel.secret%'],
            TableController::class => [Tables::class, TableOperations::class, TableGuard::class, 'twig', 'translator'],
            TableComponent::class => [Tables::class, TableViews::class, TableOperations::class, TableGuard::class, TablePreferences::class, 'request_stack', 'translator', TableExports::class],
            ExportRows::class => [StateResolver::class, RowValues::class, 'translator'],
            TableExports::class => [ExportWriters::class, 'router', TableAccess::class],
            TableExportController::class => [Tables::class, TableGuard::class, ExportRows::class, TableExports::class, 'twig', 'translator'],
            DataTableExtension::class => [KitComponents::class, CellBlocks::class],
        ];

        foreach ($names as $service => $arguments) {
            self::assertSame($arguments, $this->arguments($container, $service), $service);
        }

        self::assertSame([PropertyAccess::class, 'createPropertyAccessor'], $container->getDefinition('phil_data_table.property_accessor')->getFactory());
        self::assertSame([ExportWriters::class, 'defaults'], $container->getDefinition(ExportWriters::class)->getFactory());
        self::assertTrue($container->getDefinition(TableExportController::class)->isPublic());
        self::assertSame([null], $container->getDefinition(KitComponents::class)->getArguments());
        self::assertTrue($container->getDefinition(DataTableExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(DataTableExtension::class)->hasTag('twig.runtime'));
        self::assertSame(['ui'], $this->load(['kit_subdirectory' => 'ui'])->getDefinition(KitComponents::class)->getArguments());
        self::assertSame([[]], $container->getDefinition(CellBlocks::class)->getArguments());
        self::assertSame([['method' => 'reset']], $container->getDefinition(CellBlocks::class)->getTag('kernel.reset'));
        self::assertSame([['cells/theme.html.twig', 'cells/more.html.twig']], $this->load(['cell_themes' => ['cells/theme.html.twig', 'cells/more.html.twig']])->getDefinition(CellBlocks::class)->getArguments());
        self::assertTrue($container->hasDefinition(StateResolver::class));
        self::assertTrue($container->hasDefinition(DenyingTableAccess::class));
        self::assertTrue($container->hasDefinition(NoTablePreferences::class));
        self::assertTrue($container->getDefinition(TableComponent::class)->isAutoconfigured());

        [$tables, $access] = $container->getDefinition(Tables::class)->getArguments();
        self::assertInstanceOf(AbstractArgument::class, $tables);
        self::assertInstanceOf(Reference::class, $access);
        self::assertSame(TableAccess::class, (string) $access);
    }

    public function testSecurityAndTheSettingsAreFoundWhenThere(): void
    {
        $container = $this->load([], ['security', 'phil_settings']);

        self::assertSame(['security.authorization_checker'], $this->arguments($container, AuthorizationCheckerAccess::class));
        self::assertSame([Settings::class, CurrentUser::class, 'logger'], $this->arguments($container, SettingsTablePreferences::class));

        self::assertSame(AuthorizationCheckerAccess::class, (string) $container->getAlias(TableAccess::class));
        self::assertSame(SettingsTablePreferences::class, (string) $container->getAlias(TablePreferences::class));
    }

    public function testTheApplicationChooses(): void
    {
        $container = $this->load(['access' => 'my.access', 'preferences' => 'my.preferences'], ['security', 'phil_settings']);

        self::assertSame('my.access', (string) $container->getAlias(TableAccess::class));
        self::assertSame('my.preferences', (string) $container->getAlias(TablePreferences::class));
        self::assertFalse($container->hasDefinition(AuthorizationCheckerAccess::class));
    }

    public function testThePrependedConfiguration(): void
    {
        $container = $this->load([], ['framework', 'phil_settings'], prepended: true);
        $framework = $container->getExtensionConfig('framework');

        self::assertContains(['csrf_protection' => ['stateless_token_ids' => [TableGuard::TOKEN_ID]]], $framework);
        self::assertContains(['asset_mapper' => ['paths' => [PhilDataTableBundle::CONTROLLERS_DIR => '@phil/data-table-bundle', PhilDataTableBundle::STYLES_DIR => 'phil-data-table']]], $framework);
        self::assertSame([['settings' => [SettingsTablePreferences::KEY => ['type' => 'text', 'default' => '{}', 'section' => 'general', 'max_length' => SettingsTablePreferences::MAX_LENGTH, 'translation_domain' => 'phil_data_table']]]], $container->getExtensionConfig('phil_settings'));
    }

    public function testNothingIsPrependedToWhatIsNotThere(): void
    {
        $container = $this->load([], [], prepended: true);

        self::assertSame([], $container->getExtensionConfig('framework'));
    }

    public function testTheCsrfCheckIsWatched(): void
    {
        $container = new ContainerBuilder();
        new PhilDataTableBundle()->build($container);

        $passes = $container->getCompilerPassConfig()->getBeforeOptimizationPasses();
        self::assertTrue(array_any($passes, static fn (object $pass): bool => $pass instanceof CsrfProtectionPass));
        self::assertTrue(array_any($passes, static fn (object $pass): bool => $pass instanceof DataTablesPass));
        self::assertTrue(array_any($passes, static fn (object $pass): bool => $pass instanceof KitSubdirectoryPass));
    }

    /**
     * @return list<string>
     */
    private function arguments(ContainerBuilder $container, string $id): array
    {
        return array_values(array_map(static fn (mixed $argument): string => $argument instanceof Reference ? (string) $argument : (\is_string($argument) ? $argument : ''), $container->getDefinition($id)->getArguments()));
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string>         $extensions
     */
    private function load(array $config, array $extensions = [], bool $prepended = false): ContainerBuilder
    {
        $container = new ContainerBuilder(new ParameterBag(['kernel.environment' => 'test', 'kernel.build_dir' => sys_get_temp_dir(), 'kernel.debug' => false, 'kernel.bundles' => [], 'kernel.bundles_metadata' => [], 'kernel.project_dir' => sys_get_temp_dir()]));

        foreach ($extensions as $alias) {
            $container->registerExtension(new class($alias) extends Extension {
                public function __construct(private readonly string $name)
                {
                }

                public function getAlias(): string
                {
                    return $this->name;
                }

                public function load(array $configs, ContainerBuilder $container): void
                {
                }
            });
        }

        $bundle = new PhilDataTableBundle();
        $extension = $bundle->getContainerExtension();
        Assert::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container->registerExtension($extension);
        $container->prependExtensionConfig('phil_data_table', $config);

        if ($prepended) {
            $extension->prepend($container);

            return $container;
        }

        $extension->load([$config], $container);

        return $container;
    }
}
