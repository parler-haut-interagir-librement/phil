<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Tests\Unit;

use Phil\WaitlistBundle\PhilWaitlistBundle;
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

        self::assertSame(['bundles' => [PhilWaitlistBundle::class => ['all']]], $manifest);
        self::assertSame(['.', '..', 'manifest.json'], scandir(self::RECIPE), 'the defaults need no configuration file');

        $extension = new PhilWaitlistBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertNotNull($configuration);
        self::assertSame(
            ['offer_window' => 172_800, 'max_places' => 10, 'policy' => 'first_in_line', 'batch' => 100, 'notifications' => ['joined' => null, 'offered' => 'waitlist.offered', 'accepted' => null, 'lapsed' => 'waitlist.lapsed']],
            new Processor()->processConfiguration($configuration, [[]]),
        );
    }
}
