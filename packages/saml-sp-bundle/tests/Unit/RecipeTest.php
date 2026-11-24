<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Tests\Unit;

use Phil\SamlSpBundle\PhilSamlSpBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The Flex recipe: it registers the bundle, names the files of the key pair in the environment, imports the routes
 * under `/saml`, and writes a configuration the bundle accepts, which allows localhost and plain HTTP, without browser
 * binding, in development only.
 */
#[CoversNothing]
final class RecipeTest extends TestCase
{
    private const string RECIPE = __DIR__.'/../../recipe/0.1';

    public function testTheRecipeRegistersTheBundleAndCopiesItsFiles(): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPE.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame([
            'bundles' => [PhilSamlSpBundle::class => ['all']],
            'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/'],
            'env' => ['SAML_SP_CERTIFICATE' => '%kernel.project_dir%/config/saml/sp.crt', 'SAML_SP_PRIVATE_KEY' => '%kernel.project_dir%/config/saml/sp.key'],
        ], $manifest);
        self::assertSame(['phil_saml_sp' => ['resource' => '@PhilSamlSpBundle/config/routes.php', 'prefix' => '/saml']], Yaml::parseFile(self::RECIPE.'/config/routes/phil_saml_sp.yaml'));
        self::assertFileExists(\dirname(__DIR__, 2).'/config/routes.php');
    }

    public function testTheBundleAcceptsTheConfigurationOfTheRecipe(): void
    {
        $config = Yaml::parseFile(self::RECIPE.'/config/packages/phil_saml_sp.yaml');
        self::assertIsArray($config);
        self::assertSame(['phil_saml_sp', 'when@dev'], array_keys($config));
        self::assertSame(['phil_saml_sp' => ['allow_localhost' => true, 'secure_cookie' => false, 'bind_to_browser' => false]], $config['when@dev']);

        $extension = new PhilSamlSpBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);
        $processed = new Processor()->processConfiguration($configuration, [$config['phil_saml_sp'], $config['when@dev']['phil_saml_sp']]);

        self::assertIsArray($processed['signing']);
        self::assertSame('%env(file:resolve:SAML_SP_CERTIFICATE)%', $processed['signing']['certificate']);
        self::assertSame([], $processed['identity_providers']);
        self::assertTrue($processed['allow_localhost']);
    }
}
