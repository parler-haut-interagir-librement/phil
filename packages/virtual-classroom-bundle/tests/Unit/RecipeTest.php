<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Tests\Unit;

use Phil\VirtualClassroomBundle\PhilVirtualClassroomBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The Flex recipe: it registers the bundle, names a BigBlueButton server read from the environment, allows localhost in
 * development, keeps the rooms in memory in the tests and imports the routes under /virtual-classroom.
 */
#[CoversNothing]
final class RecipeTest extends TestCase
{
    private const string RECIPE = __DIR__.'/../../recipe/0.1';

    public function testTheRecipeRegistersTheBundleAndItsEnvironment(): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPE.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame([
            'bundles' => [PhilVirtualClassroomBundle::class => ['all']],
            'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/'],
            'env' => [
                'BBB_URL' => 'https://bbb.example.com/bigbluebutton/api/',
                '#1' => 'The shared secret of the BigBlueButton server (bbb-conf --secret), in .env.local or the secrets vault',
                'BBB_SECRET' => '',
            ],
        ], $manifest, 'The recipe writes no secret anyone could read in it.');
    }

    public function testTheConfigurationPassesTheTreeOfTheBundleInEachEnvironment(): void
    {
        $config = Yaml::parseFile(self::RECIPE.'/config/packages/phil_virtual_classroom.yaml');
        self::assertIsArray($config);
        self::assertSame(['phil_virtual_classroom', 'when@dev', 'when@test'], array_keys($config));
        self::assertIsArray($config['phil_virtual_classroom']);
        self::assertIsArray($config['when@dev']);
        self::assertIsArray($config['when@test']);

        $prod = $this->processed([$config['phil_virtual_classroom']]);
        self::assertIsArray($prod['providers']);
        self::assertSame(['main'], array_keys($prod['providers']));
        self::assertIsArray($prod['providers']['main']);
        self::assertSame(
            ['bigbluebutton', '%env(BBB_URL)%', '%env(BBB_SECRET)%', true],
            [$prod['providers']['main']['type'], $prod['providers']['main']['url'], $prod['providers']['main']['secret'], $prod['providers']['main']['signed_analytics']],
        );
        self::assertSame([false, 'dbal'], [$prod['allow_localhost'], $prod['store']]);

        $dev = $this->processed([$config['phil_virtual_classroom'], $config['when@dev']['phil_virtual_classroom']]);
        self::assertTrue($dev['allow_localhost']);

        $test = $this->processed([$config['phil_virtual_classroom'], $config['when@test']['phil_virtual_classroom']]);
        self::assertSame('memory', $test['store']);
    }

    public function testTheRoutesAreImportedUnderTheirPrefix(): void
    {
        self::assertSame(
            ['phil_virtual_classroom' => ['resource' => '@PhilVirtualClassroomBundle/config/routes.php', 'prefix' => '/virtual-classroom']],
            Yaml::parseFile(self::RECIPE.'/config/routes/phil_virtual_classroom.yaml'),
        );
        self::assertFileExists(\dirname(__DIR__, 2).'/config/routes.php');
    }

    /**
     * @param list<mixed> $configs
     *
     * @return array<mixed>
     */
    private function processed(array $configs): array
    {
        $extension = new PhilVirtualClassroomBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertNotNull($configuration);

        return new Processor()->processConfiguration($configuration, $configs);
    }
}
