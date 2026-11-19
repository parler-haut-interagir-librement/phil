<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Unit;

use Phil\CommerceCatalogBundle\PhilCommerceCatalogBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The Flex recipe: it registers the bundle, whose defaults need no configuration, and imports the route of the product
 * feeds.
 */
#[CoversNothing]
final class RecipeTest extends TestCase
{
    private const string RECIPE = __DIR__.'/../../recipe/0.1';

    public function testTheRecipeRegistersTheBundleAndImportsItsRoutes(): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPE.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(['bundles' => [PhilCommerceCatalogBundle::class => ['all']], 'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/']], $manifest);
        self::assertSame(['.', '..', 'routes'], scandir(self::RECIPE.'/config'), 'the defaults need no configuration file');
        self::assertIsArray($this->processed([]));
        self::assertSame(['phil_commerce_catalog' => ['resource' => '@PhilCommerceCatalogBundle/config/routes.php']], Yaml::parseFile(self::RECIPE.'/config/routes/phil_commerce_catalog.yaml'));
        self::assertFileExists(\dirname(__DIR__, 2).'/config/routes.php');
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<mixed>
     */
    private function processed(array $config): array
    {
        $extension = new PhilCommerceCatalogBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertNotNull($configuration);

        return new Processor()->processConfiguration($configuration, [$config]);
    }
}
