<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application with AssetMapper and StimulusBundle, in a project of the test's own, whose `assets/controllers.json`
 * enables the controllers of the bundle the way Symfony Flex writes it.
 */
final class AssetsKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(private readonly string $project)
    {
        parent::__construct('test', true);
    }

    /**
     * Replaces the trait's own, which reads `config/bundles.php` of the project.
     */
    private function getBundlesPath(): string
    {
        return __DIR__.'/config/bundles-with-assets.php';
    }

    #[Override]
    public function getProjectDir(): string
    {
        return $this->project;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return $this->project.'/var/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->project.'/var/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
        ]);

        $container->services()->set('logger', NullLogger::class);
    }
}
