<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Tests\Unit;

use Phil\AccessibilityStatementBundle\PhilAccessibilityStatementBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The Flex recipe: it registers the bundle, publishes no statement until the application writes one — the page claims
 * nothing about a site no one has evaluated — and imports the page; the configuration it writes is one the bundle
 * accepts.
 */
#[CoversNothing]
final class RecipeTest extends TestCase
{
    private const string RECIPE = __DIR__.'/../../recipe/0.1';

    public function testTheRecipeRegistersTheBundleAndCopiesItsFiles(): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPE.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(['bundles' => [PhilAccessibilityStatementBundle::class => ['all']], 'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/']], $manifest);
        self::assertSame(['phil_accessibility_statement' => ['resource' => '@PhilAccessibilityStatementBundle/config/routes.php']], Yaml::parseFile(self::RECIPE.'/config/routes/phil_accessibility_statement.yaml'));
        self::assertFileExists(\dirname(__DIR__, 2).'/config/routes.php');
    }

    public function testTheBundleAcceptsTheConfigurationOfTheRecipe(): void
    {
        $config = Yaml::parseFile(self::RECIPE.'/config/packages/phil_accessibility_statement.yaml');
        self::assertIsArray($config);
        self::assertSame(['phil_accessibility_statement'], array_keys($config));

        $extension = new PhilAccessibilityStatementBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);
        $processed = new Processor()->processConfiguration($configuration, [$config['phil_accessibility_statement'] ?? []]);

        self::assertSame('config', $processed['source']);
        self::assertNull($processed['statement'], 'no statement is published until the application writes one');
    }
}
