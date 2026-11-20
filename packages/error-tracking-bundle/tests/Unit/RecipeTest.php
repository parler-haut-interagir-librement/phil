<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Unit;

use Phil\ErrorTrackingBundle\PhilErrorTrackingBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The Flex recipe: it registers the bundle, copies a configuration the bundle accepts as is, with the administration
 * pages on, and imports their routes.
 */
#[CoversNothing]
final class RecipeTest extends TestCase
{
    private const string RECIPE = __DIR__.'/../../recipe/0.1';

    public function testTheRecipeRegistersTheBundleAndCopiesItsConfiguration(): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPE.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(['bundles' => [PhilErrorTrackingBundle::class => ['all']], 'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/']], $manifest);
        self::assertSame(['phil_error_tracking' => ['release' => '%env(default::APP_RELEASE)%', 'admin' => ['enabled' => true, 'role' => 'ROLE_ADMIN']]], Yaml::parseFile(self::RECIPE.'/config/packages/phil_error_tracking.yaml'));
        self::assertSame(['phil_error_tracking' => ['resource' => '@PhilErrorTrackingBundle/config/routes.php', 'prefix' => '/admin/errors']], Yaml::parseFile(self::RECIPE.'/config/routes/phil_error_tracking.yaml'));
        self::assertFileExists(\dirname(__DIR__, 2).'/config/routes.php');
        self::assertFileExists(\dirname(__DIR__, 2).'/config/routes_browser.php');
    }

    public function testTheBundleAcceptsTheCopiedConfiguration(): void
    {
        /** @var array{phil_error_tracking: array<string, mixed>} $config */
        $config = Yaml::parseFile(self::RECIPE.'/config/packages/phil_error_tracking.yaml');
        $extension = new PhilErrorTrackingBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        $processed = new Processor()->processConfiguration($configuration, [$config['phil_error_tracking']]);

        self::assertSame('%env(default::APP_RELEASE)%', $processed['release']);
        self::assertIsArray($processed['admin']);
        self::assertTrue($processed['admin']['enabled']);
        self::assertSame('ROLE_ADMIN', $processed['admin']['role']);
        self::assertIsArray($processed['browser']);
        self::assertFalse($processed['browser']['enabled']);
    }
}
