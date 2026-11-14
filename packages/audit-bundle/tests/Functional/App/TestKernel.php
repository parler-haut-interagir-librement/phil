<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle and the bundle under an empty `phil_audit` block
 * — or under the block a test hands the constructor.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $auditConfig the `phil_audit` block
     */
    public function __construct(string $environment, bool $debug, private readonly array $auditConfig = [])
    {
        parent::__construct($environment, $debug);
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        // One container per configuration: the block is compiled into it.
        return sys_get_temp_dir().'/phil-audit-bundle-test/'.$this->configurationHash().'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-audit-bundle-test/'.$this->configurationHash().'/log';
    }

    private function configurationHash(): string
    {
        return hash('xxh128', __DIR__.json_encode($this->auditConfig, \JSON_THROW_ON_ERROR));
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

        // Nothing the tests do is meant to be logged; the default logger would print it.
        $container->services()->set('logger', NullLogger::class);

        $container->extension('phil_audit', $this->auditConfig);
    }
}
