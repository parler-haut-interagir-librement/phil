<?php

declare(strict_types=1);

namespace Phil\UrlBundle\Tests\Functional\WithoutDoctrine;

use Override;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application without Doctrine: FrameworkBundle with forms and the validator, and the bundle with the configuration
 * the test gives it, the bundles listed in `config/bundles.php`. Not in debug, it writes nothing beside them.
 */
final class KernelWithoutDoctrine extends Kernel
{
    use MicroKernelTrait;

    private readonly string $varDir;

    /**
     * @param array<string, mixed> $urlConfig
     */
    public function __construct(private readonly array $urlConfig = [])
    {
        parent::__construct('test', false);
        $this->varDir = sys_get_temp_dir().'/phil-url-bundle-test/without-doctrine-'.bin2hex(random_bytes(8));
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return $this->varDir.'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->varDir.'/log';
    }

    /**
     * A directory of its own: not in debug, a container in cache is never rebuilt, whatever the configuration.
     */
    public function varDir(): string
    {
        return $this->varDir;
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
            'validation' => true,
        ]);
        $container->extension('phil_url', $this->urlConfig);
        $container->services()->alias('test.form.factory', 'form.factory')->public();
    }
}
