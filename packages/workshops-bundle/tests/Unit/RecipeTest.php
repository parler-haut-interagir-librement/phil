<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Tests\Unit;

use Phil\WorkshopsBundle\PhilWorkshopsBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;

/**
 * The Flex recipe: it registers the bundle, whose defaults need no configuration.
 */
#[CoversNothing]
final class RecipeTest extends TestCase
{
    private const string RECIPE = __DIR__.'/../../recipe/0.1';

    public function testTheRecipeRegistersTheBundleOnItsDefaults(): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPE.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(['bundles' => [PhilWorkshopsBundle::class => ['all']]], $manifest);
        self::assertSame(['.', '..', 'manifest.json'], scandir(self::RECIPE), 'the defaults need no configuration file');

        $extension = new PhilWorkshopsBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertNotNull($configuration);
        self::assertSame(['max_seats' => 10, 'waitlist' => true], new Processor()->processConfiguration($configuration, [[]]));
    }
}
