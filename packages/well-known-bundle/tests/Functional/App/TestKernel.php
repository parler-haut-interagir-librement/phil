<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application with every well-known document on, a resolver of its accounts, a NodeInfo provider, a signer, and a
 * document of its own; the environment `off` turns every document off.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-well-known-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-well-known-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'router' => ['default_uri' => 'https://example.com'],
        ]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set('logger', NullLogger::class);

        if ('off' === $this->environment) {
            $container->extension('phil_well_known', []);

            return;
        }

        $container->extension('phil_well_known', [
            'cache_max_age' => 600,
            'security_txt' => [
                'contact' => ['mailto:security@example.com', 'https://example.com/security'],
                'expires' => '2099-12-31T00:00:00Z',
                'encryption' => ['https://example.com/pgp-key.txt'],
                'preferred_languages' => ['fr', 'en'],
                'canonical' => ['https://example.com/.well-known/security.txt'],
                'policy' => ['https://example.com/disclosure'],
                'signer' => PrefixSigner::class,
            ],
            'change_password' => ['route' => 'account_password', 'parameters' => ['tab' => 'security']],
            'webfinger' => ['resources' => [
                'ACCT:site@EXAMPLE.com' => [
                    'aliases' => ['https://example.com/'],
                    'properties' => ['http://schema.org/name' => 'Example'],
                    'links' => [['rel' => 'http://webfinger.net/rel/profile-page', 'type' => 'text/html', 'href' => 'https://example.com/', 'titles' => ['en' => 'Home']]],
                ],
            ]],
            'nodeinfo' => ['provider' => CountingProvider::class, 'protocols' => ['activitypub'], 'services' => ['outbound' => ['rss2.0']]],
            'did' => ['file' => '%kernel.project_dir%/files/did.json'],
            'did_configuration' => ['files' => ['%kernel.project_dir%/files/credential.jwt'], 'linked_dids' => [['@context' => ['https://www.w3.org/2018/credentials/v1'], 'type' => ['VerifiableCredential', 'DomainLinkageCredential']]]],
            'atproto' => ['did' => 'did:plc:ewvi7nxzyoun6zhxrhs64oiz', 'hosts' => ['Alice.Example.com' => 'did:plc:alice']],
            'apple_app_site_association' => ['document' => ['applinks' => ['details' => [['appIDs' => ['ABCDE12345.com.example.app'], 'components' => [['/' => '/events/*']]]]]]],
            'asset_links' => ['android_apps' => [['package_name' => 'com.example.app', 'sha256_cert_fingerprints' => [implode(':', array_fill(0, 32, 'AB'))]]]],
            'documents' => [
                'matrix/server' => ['file' => '%kernel.project_dir%/files/matrix-server.json', 'content_type' => 'application/json', 'cors' => true],
                'traffic-advice' => ['content' => '[{"user_agent":"prefetch-proxy","fraction":0.5}]', 'content_type' => 'application/trafficadvice+json'],
            ],
        ]);

        $services->set(PrefixSigner::class);
        $services->set(ProfileResolver::class);
        $services->set(CountingProvider::class);
        $services->set(MatrixClientDocument::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@PhilWellKnownBundle/config/routes.php');
        $routes->add('account_password', '/account/password');
    }
}
