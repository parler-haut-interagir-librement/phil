<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Functional\App;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Override;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application with a firewall, Twig, Messenger, a lock and an SQLite database in memory (`rights/config/bundles.php`):
 * the rights of a member, end to end, with the cookie inventory of `rights/config/privacy/cookies.yaml`. The
 * `phil_privacy` configuration is the kernel's argument.
 */
final class RightsKernel extends Kernel
{
    use MicroKernelTrait;

    public const array CONFIGURATION = [
        'consent' => [
            'version' => '2',
            'categories' => [
                'session' => ['necessary' => true, 'labels' => ['en' => 'Session']],
                'analytics' => ['labels' => ['en' => 'Measurement']],
            ],
        ],
        'retention' => [
            'rules' => [
                'R-1' => ['store' => 'phil_privacy_consent_proof', 'period' => 'P1Y'],
            ],
        ],
    ];

    /**
     * @param array<string, mixed> $configuration
     */
    public function __construct(private readonly array $configuration = self::CONFIGURATION)
    {
        parent::__construct('test', true);
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__.'/rights';
    }

    #[Override]
    public function getCacheDir(): string
    {
        // One container per configuration: it is compiled into it.
        return $this->varDir().'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->varDir().'/log';
    }

    private function varDir(): string
    {
        return sys_get_temp_dir().'/phil-privacy-bundle-rights/'.$this->fingerprint();
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
            'csrf_protection' => ['enabled' => true],
            'lock' => 'flock',
            'messenger' => ['default_bus' => 'messenger.bus.default', 'buses' => ['messenger.bus.default' => null]],
        ]);
        $container->extension('twig', ['default_path' => __DIR__.'/templates']);
        $container->extension('security', [
            'firewalls' => ['main' => ['stateless' => true, 'custom_authenticators' => [HeaderAuthenticator::class], 'entry_point' => HeaderAuthenticator::class]],
        ]);
        $container->extension('phil_privacy', ['account_deletion' => ['export_directory' => $this->varDir().'/exports']]);
        $container->extension('phil_privacy', $this->configuration);

        $services = $container->services();
        $services->set(HeaderAuthenticator::class);
        $services->set(TestModule::class)->autoconfigure()->public();
        $services->set(PageController::class)->autowire()->public()->tag('controller.service_arguments');
        $services->set(EmbedController::class)->autowire()->public()->tag('controller.service_arguments');
        $services->set(RecordingGuestLinks::class)->public();
        $services->set('database_connection', Connection::class)
            ->factory(DriverManager::getConnection(...))
            ->args([['driver' => 'pdo_sqlite', 'memory' => true]])
            ->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('page', '/page')->controller(PageController::class);
        $routes->add('embed', '/embed')->controller([EmbedController::class, 'embed']);
        $routes->add('tracker', '/tracker')->controller([EmbedController::class, 'tracker']);
        $routes->add('catch_all', '/{anything}')->controller(PageController::class)->requirements(['anything' => '.+']);
    }
}
