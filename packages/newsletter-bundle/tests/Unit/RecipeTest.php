<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit;

use Phil\NewsletterBundle\PhilNewsletterBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The Flex recipe: it registers the bundle, names the key of the signed links read from the environment, imports the
 * routes under /newsletter, and writes a configuration the bundle accepts.
 */
#[CoversNothing]
final class RecipeTest extends TestCase
{
    private const string RECIPE = __DIR__.'/../../recipe/0.1';

    private const string SECRET = 'change-me-to-a-random-secret-of-32-bytes-or-more';

    public function testTheRecipeRegistersTheBundleAndCopiesItsFiles(): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPE.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame([
            'bundles' => [PhilNewsletterBundle::class => ['all']],
            'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/'],
            'env' => ['NEWSLETTER_SECRET' => self::SECRET],
        ], $manifest);
        self::assertGreaterThanOrEqual(32, \strlen(self::SECRET));
        self::assertSame(
            ['phil_newsletter' => ['resource' => '@PhilNewsletterBundle/config/routes.php', 'prefix' => '/newsletter']],
            Yaml::parseFile(self::RECIPE.'/config/routes/phil_newsletter.yaml'),
        );
        self::assertFileExists(\dirname(__DIR__, 2).'/config/routes.php');
    }

    public function testTheBundleAcceptsTheConfigurationOfTheRecipe(): void
    {
        $config = Yaml::parseFile(self::RECIPE.'/config/packages/phil_newsletter.yaml');
        self::assertIsArray($config);
        self::assertSame(['phil_newsletter'], array_keys($config));

        $extension = new PhilNewsletterBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        $processed = new Processor()->processConfiguration($configuration, [$config['phil_newsletter']]);

        self::assertSame('%env(NEWSLETTER_SECRET)%', $processed['secret']);
        self::assertSame(['default' => ['base_url' => 'https://example.com']], $processed['sites']);
        self::assertIsArray($processed['profiles']);
        self::assertIsArray($processed['profiles']['default']);
        self::assertSame('news@example.com', $processed['profiles']['default']['from']);
    }
}
