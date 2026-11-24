<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Tests\Functional;

use Override;
use Phil\FaqBundle\PhilFaqBundle;
use Phil\FaqBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The bundle's Stimulus controller found through AssetMapper, with AssetMapperBundle and StimulusBundle registered and
 * the controller enabled in the application's `controllers.json`, the way Symfony Flex writes it from the package's
 * `package.json`.
 */
#[CoversClass(PhilFaqBundle::class)]
final class StimulusControllerTest extends TestCase
{
    private string $project;

    private ?TestKernel $kernel = null;

    #[Override]
    protected function setUp(): void
    {
        $package = \dirname(__DIR__, 2);
        $this->project = sys_get_temp_dir().'/phil-faq-bundle-assets-'.bin2hex(random_bytes(4));
        $filesystem = new Filesystem();
        $filesystem->mkdir([$this->project.'/assets/controllers', $this->project.'/vendor/phil']);
        $filesystem->symlink($package, $this->project.'/vendor/phil/faq-bundle');

        /** @var array{symfony: array{controllers: array<string, array{enabled: bool, fetch: string}>}} $manifest */
        $manifest = json_decode((string) file_get_contents($package.'/assets/package.json'), true, flags: \JSON_THROW_ON_ERROR);
        $controllers = [];

        foreach ($manifest['symfony']['controllers'] as $name => $controller) {
            $controllers[$name] = ['enabled' => $controller['enabled'], 'fetch' => $controller['fetch']];
        }

        file_put_contents($this->project.'/assets/controllers.json', json_encode(['controllers' => ['@phil/faq-bundle' => $controllers], 'entrypoints' => []], \JSON_THROW_ON_ERROR));
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        new Filesystem()->remove($this->project);
    }

    public function testTheControllerAndItsModulesAreMapped(): void
    {
        $this->kernel = new TestKernel('test', true, projectDir: $this->project);
        $this->kernel->boot();

        $container = $this->kernel->getContainer()->get('test.service_container');
        $this->assertInstanceOf(ContainerInterface::class, $container);
        $mapper = $container->get('asset_mapper');
        $this->assertInstanceOf(AssetMapperInterface::class, $mapper);

        self::assertNotNull($mapper->getAsset('@phil/faq-bundle/faq_controller.js'));
        self::assertNotNull($mapper->getAsset('@phil/faq-bundle/lib/search.js'));
        self::assertNotNull($mapper->getAsset('@phil/faq-bundle/lib/voted.js'));
    }
}
