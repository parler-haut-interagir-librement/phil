<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Tests\Functional;

use Override;
use Phil\CommercePosBundle\PhilCommercePosBundle;
use Phil\CommercePosBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\UX\StimulusBundle\StimulusBundle;

/**
 * The module's Stimulus controller found by StimulusBundle through AssetMapper, as an application enables it in its
 * `controllers.json` — the way Symfony Flex writes it from the package's `package.json` —, and fetched lazily.
 */
#[CoversClass(PhilCommercePosBundle::class)]
final class StimulusControllersTest extends TestCase
{
    private string $project;

    private ?TestKernel $kernel = null;

    #[Override]
    protected function setUp(): void
    {
        if (!class_exists(StimulusBundle::class) || !ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('StimulusBundle, AssetMapper and a kernel that boots are needed.');
        }

        $package = \dirname(__DIR__, 2);
        $this->project = sys_get_temp_dir().'/phil-commerce-pos-bundle-assets-'.bin2hex(random_bytes(4));
        $filesystem = new Filesystem();
        $filesystem->mkdir([$this->project.'/assets/controllers', $this->project.'/vendor/phil']);
        $filesystem->symlink($package, $this->project.'/vendor/phil/commerce-pos-bundle');

        /** @var array{symfony: array{controllers: array<string, array{enabled: bool, fetch: string}>}} $manifest */
        $manifest = json_decode((string) file_get_contents($package.'/assets/package.json'), true, flags: \JSON_THROW_ON_ERROR);
        $controllers = [];

        foreach ($manifest['symfony']['controllers'] as $name => $controller) {
            $controllers[$name] = ['enabled' => $controller['enabled'], 'fetch' => $controller['fetch']];
        }

        file_put_contents($this->project.'/assets/controllers.json', json_encode(['controllers' => ['@phil/commerce-pos-bundle' => $controllers], 'entrypoints' => []], \JSON_THROW_ON_ERROR));
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->kernel?->shutdown();

        if (isset($this->project)) {
            new Filesystem()->remove($this->project);
        }
    }

    public function testTheOfflineQueueControllerIsMappedAndFetchedLazily(): void
    {
        $this->kernel = new TestKernel('test', true, projectDir: $this->project);
        $this->kernel->boot();

        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $assets = $container->get(AssetMapperInterface::class);
        self::assertInstanceOf(AssetMapperInterface::class, $assets);

        // StimulusBundle compiles the controllers map into its loader, which throws without the module's path.
        $loader = $assets->getAsset('@symfony/stimulus-bundle/controllers.js');

        self::assertNotNull($loader);
        self::assertNotNull($assets->getAsset('@phil/commerce-pos-bundle/offline_queue_controller.js'));
        self::assertStringContainsString('export const eagerControllers = {};', (string) $loader->content);
        self::assertMatchesRegularExpression('#export const lazyControllers = \{"phil--commerce-pos-bundle--offline-queue": \(\) => import\("[^"]*offline_queue_controller[^"]*\.js"\)\};#', (string) $loader->content);
    }
}
