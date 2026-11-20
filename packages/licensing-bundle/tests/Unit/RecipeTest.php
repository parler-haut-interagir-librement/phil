<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Tests\Unit;

use Phil\LicensingBundle\PhilLicensingBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The Flex recipe: it registers the bundle and copies a configuration the bundle accepts as is.
 */
#[CoversNothing]
final class RecipeTest extends TestCase
{
    private const string RECIPE = __DIR__.'/../../recipe/0.1';

    public function testTheRecipeRegistersTheBundleAndCopiesItsConfiguration(): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPE.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(['bundles' => [PhilLicensingBundle::class => ['all']], 'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/']], $manifest);
        self::assertSame(['phil_licensing' => ['printing' => ['strict' => false, 'unlicensed_subjects' => []]]], Yaml::parseFile(self::RECIPE.'/config/packages/phil_licensing.yaml'));
    }

    public function testTheBundleAcceptsTheCopiedConfiguration(): void
    {
        /** @var array{phil_licensing: array<string, mixed>} $config */
        $config = Yaml::parseFile(self::RECIPE.'/config/packages/phil_licensing.yaml');
        $extension = new PhilLicensingBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        $processed = new Processor()->processConfiguration($configuration, [$config['phil_licensing']]);

        self::assertSame(['strict' => false, 'unlicensed_subjects' => []], $processed['printing']);
        self::assertNull($processed['repository']);
        self::assertSame([], $processed['licenses']);
    }
}
