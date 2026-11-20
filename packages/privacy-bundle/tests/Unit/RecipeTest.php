<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit;

use Phil\PrivacyBundle\PhilPrivacyBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The Flex recipe: it registers the bundle and copies a configuration the bundle accepts as is, the links of the
 * emails on the origin of APP_PUBLIC_ORIGIN, null while it is unset.
 */
#[CoversNothing]
final class RecipeTest extends TestCase
{
    private const string RECIPE = __DIR__.'/../../recipe/0.2';

    public function testTheRecipeRegistersTheBundleAndCopiesItsConfiguration(): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPE.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(['bundles' => [PhilPrivacyBundle::class => ['all']], 'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/']], $manifest);
        self::assertSame(['phil_privacy' => [
            'public_origin' => '%env(default::APP_PUBLIC_ORIGIN)%',
            'log_redaction' => ['denylist' => ['*password*', '*token*', '*secret*', '*authorization*', '*cookie*', '*apikey']],
        ]], Yaml::parseFile(self::RECIPE.'/config/packages/phil_privacy.yaml'));
    }

    public function testTheBundleAcceptsTheCopiedConfiguration(): void
    {
        /** @var array{phil_privacy: array<string, mixed>} $config */
        $config = Yaml::parseFile(self::RECIPE.'/config/packages/phil_privacy.yaml');
        $extension = new PhilPrivacyBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        $processed = new Processor()->processConfiguration($configuration, [$config['phil_privacy']]);

        self::assertSame('%env(default::APP_PUBLIC_ORIGIN)%', $processed['public_origin']);
        self::assertIsArray($processed['log_redaction']);
        self::assertSame(['*password*', '*token*', '*secret*', '*authorization*', '*cookie*', '*apikey'], $processed['log_redaction']['denylist']);
    }
}
