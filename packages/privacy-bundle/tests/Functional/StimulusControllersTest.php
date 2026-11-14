<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Functional;

use Override;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Phil\PrivacyBundle\Tests\Functional\App\AssetsKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\UX\StimulusBundle\StimulusBundle;

/**
 * The bundle's Stimulus controllers found by StimulusBundle through AssetMapper, as an application enables them in its
 * `controllers.json` — the way Symfony Flex writes it from the package's `package.json` —, and fetched eagerly: the
 * consent banner and the frames held until consent are on the first page a visitor sees.
 */
#[CoversClass(PhilPrivacyBundle::class)]
final class StimulusControllersTest extends TestCase
{
    private string $project;

    private ?AssetsKernel $kernel = null;

    #[Override]
    protected function setUp(): void
    {
        FunctionalTestCase::skipWithoutEventDispatcher();

        if (!class_exists(StimulusBundle::class)) {
            self::markTestSkipped('StimulusBundle and AssetMapper are needed.');
        }

        $package = \dirname(__DIR__, 2);
        $this->project = sys_get_temp_dir().'/phil-privacy-bundle-assets-'.bin2hex(random_bytes(4));
        $filesystem = new Filesystem();
        $filesystem->mkdir([$this->project.'/assets/controllers', $this->project.'/vendor/phil']);
        $filesystem->symlink($package, $this->project.'/vendor/phil/privacy-bundle');

        /** @var array{symfony: array{controllers: array<string, array{enabled: bool, fetch: string}>}} $manifest */
        $manifest = json_decode((string) file_get_contents($package.'/assets/package.json'), true, flags: \JSON_THROW_ON_ERROR);
        $controllers = [];

        foreach ($manifest['symfony']['controllers'] as $name => $controller) {
            $controllers[$name] = ['enabled' => $controller['enabled'], 'fetch' => $controller['fetch']];
        }

        file_put_contents($this->project.'/assets/controllers.json', json_encode(['controllers' => ['@phil/privacy-bundle' => $controllers], 'entrypoints' => []], \JSON_THROW_ON_ERROR));
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->kernel?->shutdown();

        if (isset($this->project)) {
            new Filesystem()->remove($this->project);
        }
    }

    public function testTheConsentAndEmbedControllersAreMappedAndFetchedEagerly(): void
    {
        $this->kernel = new AssetsKernel($this->project);
        $this->kernel->boot();

        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $assets = $container->get(AssetMapperInterface::class);
        self::assertInstanceOf(AssetMapperInterface::class, $assets);

        // StimulusBundle compiles the controllers map into its loader, which throws without the bundle's path.
        $loader = $assets->getAsset('@symfony/stimulus-bundle/controllers.js');

        self::assertNotNull($loader);
        self::assertNotNull($assets->getAsset('@phil/privacy-bundle/consent_controller.js'));
        self::assertNotNull($assets->getAsset('@phil/privacy-bundle/embed_controller.js'));
        self::assertStringContainsString('export const lazyControllers = {};', (string) $loader->content);
        self::assertStringContainsString('"phil--privacy-bundle--consent": controller_0', (string) $loader->content);
        self::assertStringContainsString('"phil--privacy-bundle--embed": controller_1', (string) $loader->content);
    }
}
