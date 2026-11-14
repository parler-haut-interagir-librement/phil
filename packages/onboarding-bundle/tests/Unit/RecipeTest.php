<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit;

use Phil\OnboardingBundle\PhilOnboardingBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The Flex recipe: it registers the bundle, declares no flow and imports the endpoints of the checklist and the tour; the configuration it writes is one the bundle accepts.
 */
#[CoversNothing]
final class RecipeTest extends TestCase
{
    private const string RECIPE = __DIR__.'/../../recipe/0.1';

    public function testTheRecipeRegistersTheBundleAndCopiesItsFiles(): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPE.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(['bundles' => [PhilOnboardingBundle::class => ['all']], 'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/']], $manifest);
        self::assertSame(['phil_onboarding' => ['resource' => '@PhilOnboardingBundle/config/routes.php', 'prefix' => '/onboarding']], Yaml::parseFile(self::RECIPE.'/config/routes/phil_onboarding.yaml'));
        self::assertFileExists(\dirname(__DIR__, 2).'/config/routes.php');
    }

    public function testTheBundleAcceptsTheConfigurationOfTheRecipe(): void
    {
        $config = Yaml::parseFile(self::RECIPE.'/config/packages/phil_onboarding.yaml');
        self::assertIsArray($config);
        self::assertSame(['phil_onboarding'], array_keys($config));

        $extension = new PhilOnboardingBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        self::assertIsArray(new Processor()->processConfiguration($configuration, [$config['phil_onboarding']]));
    }
}
