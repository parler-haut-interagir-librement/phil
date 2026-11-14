<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Unit;

use Phil\FormsBundle\Domain\Contract\FieldSealer;
use Phil\FormsBundle\Infrastructure\Mailer\EmailNotifier;
use Phil\FormsBundle\Infrastructure\Webhook\WebhookNotifier;
use Phil\FormsBundle\PhilFormsBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Yaml\Yaml;

/**
 * The Flex recipe: it registers the bundle, writes a configuration the bundle reads as its defaults — no email, no
 * webhook, sensitive fields refused —, and imports the page of each form under `/forms`.
 */
#[CoversNothing]
final class RecipeTest extends TestCase
{
    private const string RECIPE = __DIR__.'/../../recipe/0.1';

    public function testTheRecipeRegistersTheBundleAndCopiesItsConfiguration(): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPE.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame([
            'bundles' => [PhilFormsBundle::class => ['all']],
            'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/'],
        ], $manifest);
    }

    public function testTheConfigurationOfTheRecipeIsTheBundlesDefaults(): void
    {
        $configuration = Yaml::parseFile(self::RECIPE.'/config/packages/phil_forms.yaml');
        self::assertIsArray($configuration);
        self::assertSame(['phil_forms'], array_keys($configuration));
        self::assertIsArray($configuration['phil_forms']);

        $container = new ContainerBuilder();
        $extension = new PhilFormsBundle()->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load([$configuration['phil_forms']], $container);

        self::assertFalse($container->hasDefinition(EmailNotifier::class));
        self::assertFalse($container->hasDefinition(WebhookNotifier::class));
        self::assertFalse($container->hasAlias(FieldSealer::class));
    }

    public function testTheRoutesOfTheRecipeImportThePagesUnderForms(): void
    {
        self::assertSame(
            ['phil_forms' => ['resource' => '@PhilFormsBundle/config/routes.php', 'prefix' => '/forms']],
            Yaml::parseFile(self::RECIPE.'/config/routes/phil_forms.yaml'),
        );
        self::assertFileExists(\dirname(__DIR__, 2).'/config/routes.php');
    }
}
