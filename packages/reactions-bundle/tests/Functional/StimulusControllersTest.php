<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Tests\Functional;

use Override;
use Phil\ReactionsBundle\PhilReactionsBundle;
use Phil\ReactionsBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The bundle's Stimulus controllers and stylesheet found through AssetMapper, with AssetMapperBundle and StimulusBundle
 * registered, the controllers enabled in the application's `controllers.json` — the way Symfony Flex writes it from the
 * package's `package.json` — and fetched lazily.
 */
#[CoversClass(PhilReactionsBundle::class)]
final class StimulusControllersTest extends TestCase
{
    private string $project;

    private ?TestKernel $kernel = null;

    #[Override]
    protected function setUp(): void
    {
        $package = \dirname(__DIR__, 2);
        $this->project = sys_get_temp_dir().'/phil-reactions-bundle-assets-'.bin2hex(random_bytes(4));
        $filesystem = new Filesystem();
        $filesystem->mkdir([$this->project.'/assets/controllers', $this->project.'/vendor/phil']);
        $filesystem->symlink($package, $this->project.'/vendor/phil/reactions-bundle');

        /** @var array{symfony: array{controllers: array<string, array{enabled: bool, fetch: string}>}} $manifest */
        $manifest = json_decode((string) file_get_contents($package.'/assets/package.json'), true, flags: \JSON_THROW_ON_ERROR);
        $controllers = [];

        foreach ($manifest['symfony']['controllers'] as $name => $controller) {
            $controllers[$name] = ['enabled' => $controller['enabled'], 'fetch' => $controller['fetch']];
        }

        file_put_contents($this->project.'/assets/controllers.json', json_encode(['controllers' => ['@phil/reactions-bundle' => $controllers], 'entrypoints' => []], \JSON_THROW_ON_ERROR));
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        new Filesystem()->remove($this->project);
    }

    public function testTheControllersAreMappedAndFetchedLazily(): void
    {
        $this->kernel = new TestKernel('test', true, projectDir: $this->project);
        $this->kernel->boot();

        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $assets = $container->get(AssetMapperInterface::class);
        self::assertInstanceOf(AssetMapperInterface::class, $assets);

        // StimulusBundle compiles the controllers map into its loader, which throws without the bundle's path.
        $loader = $assets->getAsset('@symfony/stimulus-bundle/controllers.js');
        self::assertNotNull($loader);
        self::assertNotNull($assets->getAsset('@phil/reactions-bundle/reactions_controller.js'));
        self::assertNotNull($assets->getAsset('@phil/reactions-bundle/picker_controller.js'));
        self::assertStringContainsString('export const eagerControllers = {};', (string) $loader->content);
        self::assertMatchesRegularExpression('#export const lazyControllers = \{"phil--reactions-bundle--reactions": \(\) => import\("[^"]*reactions_controller[^"]*\.js"\), "phil--reactions-bundle--picker": \(\) => import\("[^"]*picker_controller[^"]*\.js"\)\};#', (string) $loader->content);

        $stylesheet = $assets->getAsset('phil-reactions/reactions.css');
        self::assertNotNull($stylesheet);
        self::assertStringStartsWith('/assets/phil-reactions/reactions-', $stylesheet->publicPath);
    }
}
