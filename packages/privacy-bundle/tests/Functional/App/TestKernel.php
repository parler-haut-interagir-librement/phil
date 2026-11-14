<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Functional\App;

use Override;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle, MonologBundle with a single `test` handler that
 * keeps every record in memory, and the bundle (`config/bundles.php`). A route under the token prefix `/p/` lets a
 * request log a URL of its own through the framework.
 *
 * The `phil_privacy` configuration is the kernel's argument, so that a test can boot the same application on a
 * configuration the bundle refuses; each configuration compiles a container of its own.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public const array CONFIGURATION = [
        'log_redaction' => [
            'denylist' => ['*password*', '*token*', 'email'],
            'token_path_prefixes' => ['/p/'],
            'short_options' => ['p' => 'password'],
        ],
    ];

    /**
     * @param array<string, mixed> $configuration the `phil_privacy` configuration
     */
    public function __construct(string $environment = 'test', bool $debug = true, private readonly array $configuration = self::CONFIGURATION)
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
        // One container per configuration: it is compiled into it.
        return sys_get_temp_dir().'/phil-privacy-bundle-test/'.$this->fingerprint().'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-privacy-bundle-test/'.$this->fingerprint().'/log';
    }

    private function fingerprint(): string
    {
        return hash('xxh128', __DIR__.json_encode($this->configuration, \JSON_THROW_ON_ERROR));
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => ['utf8' => true],
        ]);

        // The logger is Monolog itself, as in an application: the processor under test is one of its processors.
        $container->extension('monolog', [
            'handlers' => ['memory' => ['type' => 'test', 'level' => 'debug']],
        ]);

        $container->services()->set(ShareController::class)->tag('controller.service_arguments')->public();

        $container->extension('phil_privacy', $this->configuration);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('share', '/p/{token}')->controller(ShareController::class);
    }
}
