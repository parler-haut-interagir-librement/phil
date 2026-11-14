<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Unit;

use Phil\DataTableBundle\PhilDataTableBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The Flex recipe: it registers the bundle, leaves the cell themes empty and imports the routes of plain forms and of the export; the configuration it writes is one the bundle accepts.
 */
#[CoversNothing]
final class RecipeTest extends TestCase
{
    private const string RECIPE = __DIR__.'/../../recipe/0.1';

    public function testTheRecipeRegistersTheBundleAndCopiesItsFiles(): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPE.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(['bundles' => [PhilDataTableBundle::class => ['all']], 'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/']], $manifest);
        self::assertSame(['phil_data_table' => ['resource' => '@PhilDataTableBundle/config/routes.php', 'prefix' => '/_tables']], Yaml::parseFile(self::RECIPE.'/config/routes/phil_data_table.yaml'));
        self::assertFileExists(\dirname(__DIR__, 2).'/config/routes.php');
    }

    public function testTheBundleAcceptsTheConfigurationOfTheRecipe(): void
    {
        $config = Yaml::parseFile(self::RECIPE.'/config/packages/phil_data_table.yaml');
        self::assertIsArray($config);
        self::assertSame(['phil_data_table'], array_keys($config));

        $extension = new PhilDataTableBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        self::assertIsArray(new Processor()->processConfiguration($configuration, [$config['phil_data_table']]));
    }
}
