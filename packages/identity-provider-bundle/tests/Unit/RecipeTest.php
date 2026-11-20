<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit;

use Phil\IdentityProviderBundle\PhilIdentityProviderBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The Flex recipes: they register the bundle, copy a configuration the bundle accepts as is and import the routes of
 * the modules; from 0.3, the links of the emails point to the origin of APP_PUBLIC_ORIGIN, null while it is unset.
 */
#[CoversNothing]
final class RecipeTest extends TestCase
{
    private const string RECIPES = __DIR__.'/../../recipe';

    /**
     * @return iterable<string, array{string}>
     */
    public static function versions(): iterable
    {
        yield '0.1' => ['0.1'];
        yield '0.3' => ['0.3'];
    }

    #[DataProvider('versions')]
    public function testTheRecipeRegistersTheBundleAndImportsTheRoutes(string $version): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPES.'/'.$version.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(['bundles' => [PhilIdentityProviderBundle::class => ['all']], 'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/']], $manifest);
        self::assertSame(['phil_identity_provider' => ['resource' => '.', 'type' => 'phil_identity_provider']], Yaml::parseFile(self::RECIPES.'/'.$version.'/config/routes/phil_identity_provider.yaml'));
    }

    #[DataProvider('versions')]
    public function testTheBundleAcceptsTheCopiedConfigurationInEveryEnvironment(string $version): void
    {
        /** @var array{phil_identity_provider: array<string, mixed>, 'when@test': array{phil_identity_provider: array<string, mixed>}} $config */
        $config = Yaml::parseFile(self::RECIPES.'/'.$version.'/config/packages/phil_identity_provider.yaml');
        $configuration = $this->configuration();

        $processed = new Processor()->processConfiguration($configuration, [$config['phil_identity_provider']]);
        $test = new Processor()->processConfiguration($configuration, [$config['phil_identity_provider'], $config['when@test']['phil_identity_provider']]);

        self::assertSame('app_login', $processed['login_route']);
        self::assertSame('memory', $test['store']);
    }

    public function testTheRecipeOfTheVersionThatRefusesToBootWithoutAnOriginReadsItFromTheEnvironment(): void
    {
        /** @var array{phil_identity_provider: array<string, mixed>} $config */
        $config = Yaml::parseFile(self::RECIPES.'/0.3/config/packages/phil_identity_provider.yaml');

        $processed = new Processor()->processConfiguration($this->configuration(), [$config['phil_identity_provider']]);

        self::assertSame('%env(default::APP_PUBLIC_ORIGIN)%', $processed['public_origin']);
    }

    private function configuration(): ConfigurationInterface
    {
        $extension = new PhilIdentityProviderBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        return $configuration;
    }
}
