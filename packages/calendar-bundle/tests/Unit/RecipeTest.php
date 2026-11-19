<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit;

use Phil\CalendarBundle\PhilCalendarBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The Flex recipe: it registers the bundle, sets the time zone and first weekday of a new calendar and imports its routes.
 */
#[CoversNothing]
final class RecipeTest extends TestCase
{
    private const string RECIPE = __DIR__.'/../../recipe/0.1';

    public function testTheRecipeRegistersTheBundleAndCopiesItsConfiguration(): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPE.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(['bundles' => [PhilCalendarBundle::class => ['all']], 'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/']], $manifest);
        $config = Yaml::parseFile(self::RECIPE.'/config/packages/phil_calendar.yaml');
        self::assertSame(['phil_calendar' => ['default_timezone' => 'UTC', 'first_weekday' => 1]], $config);
        self::assertIsArray($config['phil_calendar']);
        $processed = $this->processed($config['phil_calendar']);
        self::assertSame(['UTC', 1], [$processed['default_timezone'], $processed['first_weekday']]);
        self::assertSame(['phil_calendar' => ['resource' => '@PhilCalendarBundle/config/routes.php', 'prefix' => '/calendar']], Yaml::parseFile(self::RECIPE.'/config/routes/phil_calendar.yaml'));
        self::assertFileExists(\dirname(__DIR__, 2).'/config/routes.php');
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<mixed>
     */
    private function processed(array $config): array
    {
        $extension = new PhilCalendarBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertNotNull($configuration);

        return new Processor()->processConfiguration($configuration, [$config]);
    }
}
