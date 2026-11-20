<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Tests\Unit;

use Phil\CommerceSyndicationBundle\Infrastructure\Http\SyndicationWebhookController;
use Phil\CommerceSyndicationBundle\PhilCommerceSyndicationBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The Flex recipe: it registers the bundle, reads its identity and signing key from the environment, and routes the
 * partners' messages to the webhook.
 */
#[CoversNothing]
final class RecipeTest extends TestCase
{
    private const string RECIPE = __DIR__.'/../../recipe/0.1';

    public function testTheRecipeRegistersTheBundleWithItsRequiredKeysAndRoute(): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPE.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertIsArray($manifest);
        self::assertSame([PhilCommerceSyndicationBundle::class => ['all']], $manifest['bundles']);
        self::assertSame(['config/' => '%CONFIG_DIR%/'], $manifest['copy-from-recipe']);
        self::assertIsArray($manifest['env']);
        self::assertSame(['SYNDICATION_IDENTITY' => 'shop', 'SYNDICATION_SIGNING_KEY' => ''], array_filter($manifest['env'], static fn (string $key): bool => !str_starts_with($key, '#'), \ARRAY_FILTER_USE_KEY));

        $config = Yaml::parseFile(self::RECIPE.'/config/packages/phil_commerce_syndication.yaml');
        self::assertSame(['phil_commerce_syndication' => ['identity' => '%env(SYNDICATION_IDENTITY)%', 'signing_key' => '%env(SYNDICATION_SIGNING_KEY)%']], $config);
        self::assertIsArray($config['phil_commerce_syndication']);
        $processed = $this->processed($config['phil_commerce_syndication']);
        self::assertSame(['%env(SYNDICATION_IDENTITY)%', '%env(SYNDICATION_SIGNING_KEY)%', 300], [$processed['identity'], $processed['signing_key'], $processed['tolerance']]);

        self::assertSame(['phil_commerce_syndication' => [
            'path' => '/syndication/{partner}',
            'methods' => ['POST'],
            'controller' => SyndicationWebhookController::class,
            'requirements' => ['partner' => '[a-z0-9][a-z0-9_.-]{0,62}'],
        ]], Yaml::parseFile(self::RECIPE.'/config/routes/phil_commerce_syndication.yaml'));
    }

    public function testTheKeysTheRecipeSetsAreRequired(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->processed([]);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<mixed>
     */
    private function processed(array $config): array
    {
        $extension = new PhilCommerceSyndicationBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertNotNull($configuration);

        return new Processor()->processConfiguration($configuration, [$config]);
    }
}
