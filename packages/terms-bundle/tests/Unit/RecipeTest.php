<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Unit;

use Phil\TermsBundle\Application\Contract\MemberResolver;
use Phil\TermsBundle\Application\Contract\MemberSource;
use Phil\TermsBundle\PhilTermsBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The Flex recipe: it registers the bundle, declares two policies, names the members by the classes it copies and imports the pages; the configuration it writes is one the bundle accepts.
 */
#[CoversNothing]
final class RecipeTest extends TestCase
{
    private const string RECIPE = __DIR__.'/../../recipe/0.1';

    public function testTheRecipeRegistersTheBundleAndCopiesItsFiles(): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPE.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(['bundles' => [PhilTermsBundle::class => ['all']], 'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/', 'src/' => '%SRC_DIR%/']], $manifest);
        self::assertSame(['phil_terms' => ['resource' => '@PhilTermsBundle/config/routes/pages.php', 'prefix' => '/terms']], Yaml::parseFile(self::RECIPE.'/config/routes/phil_terms.yaml'));
        self::assertFileExists(\dirname(__DIR__, 2).'/config/routes/pages.php');
    }

    public function testTheBundleAcceptsTheConfigurationOfTheRecipe(): void
    {
        $config = Yaml::parseFile(self::RECIPE.'/config/packages/phil_terms.yaml');
        self::assertIsArray($config);
        self::assertSame(['phil_terms'], array_keys($config));

        $extension = new PhilTermsBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        self::assertIsArray(new Processor()->processConfiguration($configuration, [$config['phil_terms']]));
    }

    public function testTheCopiedClassesNameNobody(): void
    {
        // The classes live in the application once copied; here they are loaded from the recipe by their file.
        require_once self::RECIPE.'/src/Terms/Members.php';
        require_once self::RECIPE.'/src/Terms/CurrentMember.php';
        $members = $this->instantiate('App\\Terms\\Members');
        $current = $this->instantiate('App\\Terms\\CurrentMember');

        self::assertInstanceOf(MemberSource::class, $members);
        self::assertInstanceOf(MemberResolver::class, $current);
        self::assertSame([], [...$members->members()]);
        self::assertNull($current->current());
    }

    /**
     * An object of a class the bundle does not know, so that static analysis reads it through the interface checked.
     */
    private function instantiate(string $class): object
    {
        return new $class();
    }
}
