<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Unit;

use Phil\ApiKeysBundle\PhilApiKeysBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The Flex recipe: it registers the bundle and reads the key of the hashes from the environment; the members' page and
 * its routes stay for the application to enable.
 */
#[CoversNothing]
final class RecipeTest extends TestCase
{
    private const string RECIPE = __DIR__.'/../../recipe/0.1';

    public function testTheRecipeRegistersTheBundleWithItsRequiredKey(): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPE.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertIsArray($manifest);
        self::assertSame([PhilApiKeysBundle::class => ['all']], $manifest['bundles']);
        self::assertSame(['config/' => '%CONFIG_DIR%/'], $manifest['copy-from-recipe']);
        self::assertIsArray($manifest['env']);
        self::assertSame(['API_KEYS_HASH_KEY' => ''], array_filter($manifest['env'], static fn (string $key): bool => !str_starts_with($key, '#'), \ARRAY_FILTER_USE_KEY));

        $config = Yaml::parseFile(self::RECIPE.'/config/packages/phil_api_keys.yaml');
        self::assertSame(['phil_api_keys' => ['prefix' => 'pat', 'hash_key' => '%env(API_KEYS_HASH_KEY)%']], $config);
        self::assertIsArray($config['phil_api_keys']);
        $processed = $this->processed($config['phil_api_keys']);
        self::assertIsArray($processed['member_page']);
        self::assertSame(['pat', ['%env(API_KEYS_HASH_KEY)%'], false], [$processed['prefix'], $processed['hash_key'], $processed['member_page']['enabled']]);
        self::assertDirectoryDoesNotExist(self::RECIPE.'/config/routes');
        self::assertFileExists(\dirname(__DIR__, 2).'/config/routes.php');
    }

    public function testTheKeyTheRecipeSetsIsRequired(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->processed(['prefix' => 'pat']);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<mixed>
     */
    private function processed(array $config): array
    {
        $extension = new PhilApiKeysBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertNotNull($configuration);

        return new Processor()->processConfiguration($configuration, [$config]);
    }
}
