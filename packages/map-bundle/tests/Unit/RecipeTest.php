<?php

declare(strict_types=1);

namespace Phil\MapBundle\Tests\Unit;

use Phil\MapBundle\PhilMapBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * The Flex recipe: it registers the bundle, configures the proxied tiles and imports the proxy's route.
 */
#[CoversNothing]
final class RecipeTest extends TestCase
{
    private const string RECIPE = __DIR__.'/../../recipe/0.1';

    public function testTheRecipeRegistersTheBundleAndCopiesItsConfiguration(): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPE.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(['bundles' => [PhilMapBundle::class => ['all']], 'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/']], $manifest);
        self::assertSame(['phil_map' => ['tiles' => ['source' => 'proxy', 'proxy' => ['user_agent' => 'phil/map-bundle tile proxy']]]], Yaml::parseFile(self::RECIPE.'/config/packages/phil_map.yaml'));
        self::assertSame(['phil_map' => ['resource' => '@PhilMapBundle/config/routes.php', 'prefix' => '/_map']], Yaml::parseFile(self::RECIPE.'/config/routes/phil_map.yaml'));
        self::assertFileExists(\dirname(__DIR__, 2).'/config/routes.php');
    }
}
