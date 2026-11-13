<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Tests\Functional\App;

use Override;
use Phil\PagedMediaBundle\Application\Event\PdfStored;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle with its HTTP client played by `GotenbergResponses`
 * and Messenger handling messages at once, the bundle configured with Gotenberg, a vault certificate and a timestamp
 * authority, a ticket template, and a cache pool for the jobs.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $config the bundle's configuration
     */
    public function __construct(private readonly array $config = [], string $environment = 'test')
    {
        parent::__construct($environment, true);
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-paged-media-bundle-test/'.hash('xxh128', __DIR__.json_encode($this->config, \JSON_THROW_ON_ERROR)).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-paged-media-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test', 'test' => true, 'http_method_override' => false, 'php_errors' => ['log' => true],
            'http_client' => ['mock_response_factory' => GotenbergResponses::class],
            'messenger' => ['enabled' => true],
            'cache' => ['pools' => ['paged_media.jobs' => ['adapter' => 'cache.adapter.array', 'default_lifetime' => 3600]]],
        ]);
        $container->extension('phil_paged_media', $this->config);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set(GotenbergResponses::class)->public();
        $services->set(Ticket::class);
        $services->set(StoredFiles::class)->public()->tag('kernel.event_listener', ['event' => PdfStored::class]);
    }
}
