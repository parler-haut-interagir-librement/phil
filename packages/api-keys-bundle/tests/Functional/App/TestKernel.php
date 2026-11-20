<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * An application as small as the bundle needs: FrameworkBundle with forms, CSRF and the translator in `fr`;
 * SecurityBundle with a stateless firewall for the API under `/api`, on the `access_token` authenticator with the
 * bundle's handler and problem details, a session firewall for the members' page under `/account/tokens`, and none for
 * the endpoint of GitHub's secret scanning under `/hooks`; TwigBundle; DoctrineBundle on SQLite; problem details under
 * `/api`; a rate limiter of three requests an hour per token; and the bundle, the address of the last use truncated,
 * GitHub's keys fetched by an HTTP client the test sets.
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
        return sys_get_temp_dir().'/phil-api-keys-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getShareDir(): string
    {
        return sys_get_temp_dir().'/phil-api-keys-bundle-test/'.hash('xxh128', __DIR__).'/share';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-api-keys-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'a test secret of sixteen bytes',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'form' => true,
            'csrf_protection' => true,
            'validation' => true,
            'translator' => ['fallbacks' => ['fr']],
            'default_locale' => 'fr',
        ]);
        $container->extension('security', [
            'password_hashers' => [InMemoryUser::class => 'plaintext'],
            'providers' => [
                'members' => ['memory' => ['users' => [
                    'alice@example.com' => ['password' => 'secret', 'roles' => ['ROLE_MEMBER']],
                ]]],
                'organizations' => ['memory' => ['users' => [
                    'acme' => ['password' => '', 'roles' => ['ROLE_ORGANIZATION']],
                ]]],
            ],
            'firewalls' => [
                'github' => ['pattern' => '^/hooks/', 'stateless' => true, 'security' => false],
                'api' => [
                    'pattern' => '^/api/',
                    'stateless' => true,
                    'provider' => 'members',
                    'access_token' => [
                        'token_handler' => 'phil_api_keys.token_handler',
                        'token_extractors' => ['phil_api_keys.token_extractor'],
                        'realm' => 'api',
                        'failure_handler' => 'phil_api_keys.problem_responder',
                    ],
                    'entry_point' => 'phil_api_keys.problem_responder',
                ],
                'main' => ['provider' => 'members', 'lazy' => true],
            ],
            'access_control' => [
                ['path' => '^/api/', 'roles' => 'IS_AUTHENTICATED_FULLY'],
                ['path' => '^/account/', 'roles' => 'ROLE_MEMBER'],
            ],
        ]);
        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///%kernel.cache_dir%/tokens.sqlite'], 'orm' => []]);
        $container->extension('phil_api_contract', ['problem_details' => ['paths' => ['^/api/']]]);
        $container->extension('phil_rate_limit', ['headers' => 'legacy']);
        $container->extension('phil_usage_meter', [
            'store' => 'memory',
            'meters' => ['requests' => ['limit' => 3, 'rolling' => 3600]],
            'rate_limiters' => ['api' => ['meter' => 'requests', 'key' => 'phil_api_keys.rate_limit_key']],
        ]);
        $container->extension('phil_api_keys', [
            'prefix' => 'acme',
            'hash_key' => 'a-test-key-of-thirty-two-bytes!!',
            'scopes' => [
                'projects:read' => ['description' => 'Read the projects', 'label' => 'Lire les projets'],
                'projects:write' => ['description' => 'Change the projects', 'label' => 'Modifier les projets'],
            ],
            'owner_types' => ['organization' => ['provider' => 'security.user.provider.concrete.organizations', 'scopes' => ['projects:read']]],
            'last_used_address' => 'truncation',
            'secret_scanning' => ['github' => ['http_client' => 'test.github_http_client', 'cache' => 'test.github_cache']],
            'member_page' => true,
        ]);

        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->set('clock', MockClock::class)->args(['2026-11-08 10:00:00 UTC'])->public();
        $services->set(ProjectController::class)->autowire()->tag('controller.service_arguments')->public();
        $services->set(OrganizationVoter::class)->tag('security.voter');
        $services->set('test.github_http_client')->synthetic()->public();
        $services->set('test.github_cache', ArrayAdapter::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('projects', '/api/projects')->controller([ProjectController::class, 'list'])->methods(['GET']);
        $routes->add('project_update', '/api/projects/1')->controller([ProjectController::class, 'update'])->methods(['PUT']);
        $routes->import(__DIR__.'/../../../config/routes.php')->prefix('/account/tokens');
        $routes->import(__DIR__.'/../../../config/github_secret_scanning.php')->prefix('/hooks/github/secret-scanning');
    }
}
