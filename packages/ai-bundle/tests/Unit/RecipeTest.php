<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Unit;

use Phil\AiBundle\PhilAiBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The Flex recipe: it registers the bundle and sets the currency of the costs; the platforms and tasks stay for the
 * application to declare.
 */
#[CoversNothing]
final class RecipeTest extends TestCase
{
    private const string RECIPE = __DIR__.'/../../recipe/0.1';

    public function testTheRecipeRegistersTheBundleAndCopiesItsConfiguration(): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPE.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(['bundles' => [PhilAiBundle::class => ['all']], 'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/']], $manifest);
        $config = Yaml::parseFile(self::RECIPE.'/config/packages/phil_ai.yaml');
        self::assertSame(['phil_ai' => ['currency' => 'USD']], $config);
        self::assertIsArray($config['phil_ai']);
        $processed = $this->processed($config['phil_ai']);
        self::assertSame(['USD', [], [], null], [$processed['currency'], $processed['platforms'], $processed['tasks'], $processed['usage_meter']]);
        self::assertDirectoryDoesNotExist(self::RECIPE.'/config/routes');
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<mixed>
     */
    private function processed(array $config): array
    {
        $extension = new PhilAiBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertNotNull($configuration);

        return new Processor()->processConfiguration($configuration, [$config]);
    }
}
