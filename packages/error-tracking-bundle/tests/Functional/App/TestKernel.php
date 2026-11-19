<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Functional\App;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * An application as small as the bundle needs: FrameworkBundle and DoctrineBundle on SQLite for the store, the bundles
 * it bridges to, a page that fails, the same behind a token in its path, a page that is not found, and the endpoint of
 * the browsers.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param bool $singleServer whether the application says it runs on one server, where a local pool counts everything
     */
    public function __construct(string $environment, bool $debug, private readonly bool $singleServer = false)
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
        return sys_get_temp_dir().'/phil-error-tracking-bundle-test/'.hash('xxh128', __DIR__).'/cache'.($this->singleServer ? '-single' : '');
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-error-tracking-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    public function failing(): Response
    {
        throw new RuntimeException('The export of order 12 failed for jane@example.com');
    }

    public function missing(): Response
    {
        throw new NotFoundHttpException('No such page');
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
        $services = $container->services();
        $services->set('logger', NullLogger::class);
        // An application requires FrameworkBundle, and with it the event dispatcher, in production; installed on its
        // own, the bundle has them in development only, which the container takes as absent.
        $services->set('event_dispatcher', EventDispatcher::class)->public()->tag('event_dispatcher.dispatcher', ['name' => 'event_dispatcher']);
        $services->alias(EventDispatcherInterface::class, 'event_dispatcher');
        $services->alias(PsrEventDispatcherInterface::class, 'event_dispatcher');

        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///%kernel.cache_dir%/errors.sqlite']]);
        $container->extension('phil_notifications', ['store' => 'memory', 'message_bus' => null]);
        $container->extension('phil_error_tracking', [
            'release' => '1.0.0',
            'token_path_prefixes' => ['/reset/'],
            'alerts' => match ($this->environment) {
                'chatless' => [],
                'quiet' => false,
                default => ['recipients' => ['ops']],
            },
            'rate_limit' => ['single_server' => $this->singleServer],
            'capture' => ['messenger' => false],
            'browser' => ['enabled' => true, 'token' => 's3cret', 'rate_limit' => ['single_server' => $this->singleServer]],
        ]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@PhilErrorTrackingBundle/config/routes_browser.php');
        $routes->add('failing', '/failing')->controller($this->failing(...));
        $routes->add('reset', '/reset/{token}')->controller($this->failing(...));
        $routes->add('missing', '/missing')->controller($this->missing(...));
    }
}
