<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Tests\Unit;

use Phil\ReviewsBundle\PhilReviewsBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The Flex recipe: it registers the bundle, imports its pages from the file the bundle ships, and writes a
 * configuration the bundle accepts.
 */
#[CoversNothing]
final class RecipeTest extends TestCase
{
    private const string RECIPE = __DIR__.'/../../recipe/0.1';

    public function testTheRecipeRegistersTheBundleAndImportsItsRoutes(): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPE.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(['bundles' => [PhilReviewsBundle::class => ['all']], 'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/']], $manifest);

        $routes = Yaml::parseFile(self::RECIPE.'/config/routes/phil_reviews.yaml');
        self::assertSame(['phil_reviews' => ['resource' => '@PhilReviewsBundle/config/routes.php', 'prefix' => '/reviews']], $routes);
        self::assertFileExists(\dirname(__DIR__, 2).'/config/routes.php');
    }

    public function testTheBundleAcceptsTheConfigurationOfTheRecipe(): void
    {
        $config = Yaml::parseFile(self::RECIPE.'/config/packages/phil_reviews.yaml');
        self::assertIsArray($config);
        self::assertSame(['phil_reviews'], array_keys($config));

        $extension = new PhilReviewsBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        $processed = new Processor()->processConfiguration($configuration, [$config['phil_reviews']]);

        $types = $processed['types'];
        self::assertIsArray($types);
        self::assertSame(['product'], array_keys($types));
        self::assertIsArray($types['product']);
        self::assertSame(['quality', 'value'], $types['product']['criteria']);
    }
}
