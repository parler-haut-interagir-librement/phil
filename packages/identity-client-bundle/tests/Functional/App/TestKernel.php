<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Functional\App;

use Override;
use Phil\IdentityClientBundle\Application\Contract\UserProvisioner;
use Phil\IdentityClientBundle\Application\Event\ElevationRefused;
use Phil\IdentityClientBundle\Application\Event\ElevationRequested;
use Phil\IdentityClientBundle\Application\Event\LocalSessionEnded;
use Phil\IdentityClientBundle\Application\Event\ProviderSessionEnded;
use Phil\IdentityClientBundle\Application\Event\RefreshTokenRejected;
use Phil\IdentityClientBundle\Application\Event\ReturnToDiscarded;
use Phil\IdentityClientBundle\Application\Event\TokensRefreshed;
use Phil\IdentityClientBundle\Application\Event\UserSignedIn;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $client the phil_identity_client configuration, over the defaults
     */
    public function __construct(private readonly array $client = [])
    {
        parent::__construct('test', false);
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-identity-client/'.getmypid().'/'.hash('xxh128', serialize($this->client));
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->getCacheDir().'/log';
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file', 'handler_id' => null],
            'lock' => 'flock',
            'http_client' => ['enabled' => true],
        ]);
        $container->extension('security', [
            'providers' => ['memory' => ['memory' => null]],
            'firewalls' => [
                'dev' => ['pattern' => '^/_dev', 'security' => false],
                'main' => ['lazy' => true, 'logout' => ['path' => '/logout']],
            ],
            'access_control' => [
                ['path' => '^/private', 'roles' => 'IS_AUTHENTICATED_FULLY'],
                ['path' => '^/strong', 'roles' => 'OIDC_ASSURANCE:strong'],
                ['path' => '^/fresh', 'roles' => 'OIDC_ASSURANCE:fresh'],
                ['path' => '^/recent', 'roles' => 'IS_AUTHENTICATED_RECENTLY'],
            ],
        ]);
        $container->extension('phil_identity_client', array_replace_recursive([
            'issuer' => FakeOidcProvider::ISSUER,
            'client_id' => FakeOidcProvider::CLIENT_ID,
            'client_secret' => 'a-client-secret',
            'http_client' => 'test.oidc_http_client',
            'claims' => ['name', 'email', 'locale'],
            'return_to' => ['origins' => ['https://shop.example.test', 'http://localhost']],
            'elevation' => ['levels' => [
                'basic' => ['acr' => 'urn:example:basic'],
                'strong' => ['acr' => 'urn:example:strong'],
                'fresh' => ['max_age' => 300],
            ]],
            'back_channel_logout' => ['cache' => 'test.cache'],
            'refresh' => ['enabled' => false, 'cache' => 'test.cache'],
        ], $this->client));

        $services = $container->services();
        $services->set('clock', MockClock::class)->args(['2026-11-09 12:00:00'])->public();
        $services->set('test.cache', ArrayAdapter::class);
        $services->set(FakeOidcProvider::class)->args([service('clock')])->public();
        $services->set('test.oidc_http_client', MockHttpClient::class)->args([service(FakeOidcProvider::class)]);
        $services->set(RecordingProvisioner::class)->public();
        $services->alias(UserProvisioner::class, RecordingProvisioner::class);
        $log = $services->set(EventLog::class)->public();
        foreach ([UserSignedIn::class, ProviderSessionEnded::class, LocalSessionEnded::class, ElevationRequested::class, ElevationRefused::class, TokensRefreshed::class, RefreshTokenRejected::class, ReturnToDiscarded::class] as $event) {
            $log->tag('kernel.event_listener', ['event' => $event]);
        }

        $services->set('test.page', TestPage::class)->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('security.route_loader', 'service');
        foreach (['home' => '/', 'private' => '/private', 'strong' => '/strong', 'fresh' => '/fresh', 'recent' => '/recent', 'logout' => '/logout'] as $name => $path) {
            $routes->add($name, $path)->controller('test.page');
        }
    }
}
