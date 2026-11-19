<?php

declare(strict_types=1);

namespace Phil\UsageMeterBundle\Tests\Unit;

use Phil\UsageMeterBundle\PhilUsageMeterBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The Flex recipe: it registers the bundle and keeps the counters on the default DBAL connection, without meters.
 */
#[CoversNothing]
final class RecipeTest extends TestCase
{
    private const string RECIPE = __DIR__.'/../../recipe/0.1';

    public function testTheRecipeRegistersTheBundleAndCopiesItsConfiguration(): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPE.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(['bundles' => [PhilUsageMeterBundle::class => ['all']], 'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/']], $manifest);
        $config = Yaml::parseFile(self::RECIPE.'/config/packages/phil_usage_meter.yaml');
        self::assertSame(['phil_usage_meter' => ['store' => 'dbal', 'dbal' => ['connection' => 'default'], 'meters' => []]], $config);
        self::assertIsArray($config['phil_usage_meter']);
        $processed = $this->processed($config['phil_usage_meter']);
        self::assertIsArray($processed['dbal']);
        self::assertSame(['dbal', 'default', 'phil_usage_counter', 'phil_usage_key', []], [$processed['store'], $processed['dbal']['connection'], $processed['dbal']['counter_table'], $processed['dbal']['key_table'], $processed['meters']]);
        self::assertDirectoryDoesNotExist(self::RECIPE.'/config/routes');
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<mixed>
     */
    private function processed(array $config): array
    {
        $extension = new PhilUsageMeterBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertNotNull($configuration);

        return new Processor()->processConfiguration($configuration, [$config]);
    }
}
