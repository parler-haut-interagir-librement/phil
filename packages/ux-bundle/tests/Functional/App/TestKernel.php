<?php

declare(strict_types=1);

namespace Phil\UxBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle with forms and AssetMapper, TwigBundle and PHIL UX.
 * `$config` is the bundle's configuration, `$themes` the form themes the application lists.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $config
     * @param list<string>         $themes
     */
    public function __construct(private readonly array $config = [], private readonly array $themes = [])
    {
        parent::__construct('test', true);
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return $this->varDir().'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->varDir().'/log';
    }

    private function varDir(): string
    {
        return sys_get_temp_dir().'/phil-ux-bundle-test/'.hash('xxh128', __DIR__.serialize([$this->config, $this->themes]));
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'form' => ['csrf_protection' => false],
            'asset_mapper' => ['paths' => []],
        ]);
        $container->extension('twig', ['strict_variables' => true, 'default_path' => __DIR__.'/templates', 'form_themes' => $this->themes]);
        $container->extension('phil_ux', $this->config);
        $container->services()->set('logger', NullLogger::class);
        // The tests build their forms with the factory, which nothing in this application injects.
        $container->services()->alias('test.form_factory', 'form.factory')->public();
    }
}
