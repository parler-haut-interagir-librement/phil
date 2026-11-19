<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Tests\Unit;

use Phil\DocumentImportBundle\PhilDocumentImportBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The Flex recipe: it registers the bundle and leaves the media store and the scan to what is installed.
 */
#[CoversNothing]
final class RecipeTest extends TestCase
{
    private const string RECIPE = __DIR__.'/../../recipe/0.1';

    public function testTheRecipeRegistersTheBundleAndCopiesItsConfiguration(): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPE.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(['bundles' => [PhilDocumentImportBundle::class => ['all']], 'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/']], $manifest);
        $config = Yaml::parseFile(self::RECIPE.'/config/packages/phil_document_import.yaml');
        self::assertSame(['phil_document_import' => ['media' => ['store' => 'auto'], 'scan' => 'auto']], $config);
        self::assertIsArray($config['phil_document_import']);
        $processed = $this->processed($config['phil_document_import']);
        self::assertIsArray($processed['media']);
        self::assertSame(['auto', 'auto'], [$processed['media']['store'], $processed['scan']]);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<mixed>
     */
    private function processed(array $config): array
    {
        $extension = new PhilDocumentImportBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertNotNull($configuration);

        return new Processor()->processConfiguration($configuration, [$config]);
    }
}
