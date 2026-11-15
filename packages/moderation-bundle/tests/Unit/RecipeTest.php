<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Unit;

use Phil\ModerationBundle\PhilModerationBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The Flex recipe: it registers the bundle, imports the four groups of pages from files the bundle ships, and writes a
 * configuration the bundle accepts.
 */
#[CoversNothing]
final class RecipeTest extends TestCase
{
    private const string RECIPE = __DIR__.'/../../recipe/0.1';

    public function testTheRecipeRegistersTheBundleAndImportsItsRoutes(): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPE.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(['bundles' => [PhilModerationBundle::class => ['all']], 'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/']], $manifest);

        $routes = Yaml::parseFile(self::RECIPE.'/config/routes/phil_moderation.yaml');
        self::assertIsArray($routes);
        self::assertSame(['phil_moderation_admin', 'phil_moderation_report', 'phil_moderation_journal', 'phil_moderation_statements'], array_keys($routes));
        self::assertSame(
            ['/admin/moderation', '/moderation', '/moderation/journal', '/moderation/statements'],
            array_column($routes, 'prefix'),
        );

        foreach (array_column($routes, 'resource') as $resource) {
            self::assertIsString($resource);
            self::assertStringStartsWith('@PhilModerationBundle/', $resource);
            self::assertFileExists(\dirname(__DIR__, 2).'/'.substr($resource, \strlen('@PhilModerationBundle/')));
        }
    }

    public function testTheBundleAcceptsTheConfigurationOfTheRecipe(): void
    {
        $config = Yaml::parseFile(self::RECIPE.'/config/packages/phil_moderation.yaml');
        self::assertIsArray($config);
        self::assertSame(['phil_moderation'], array_keys($config));

        $extension = new PhilModerationBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        $processed = new Processor()->processConfiguration($configuration, [$config['phil_moderation']]);

        $incident = $processed['incident'];
        $roles = $processed['roles'];
        self::assertIsArray($incident);
        self::assertIsArray($roles);
        self::assertSame('ROLE_ADMIN', $incident['role']);
        self::assertSame([], $incident['spare_established']);
        self::assertSame(['moderator', 'administrator'], array_column($roles, 'name'));
    }
}
