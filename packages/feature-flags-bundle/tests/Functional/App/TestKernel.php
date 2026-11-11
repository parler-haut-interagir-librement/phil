<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Functional\App;

use Override;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\ListAuthorizer;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\RecordingAuditor;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\RecordingExposures;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * An application as small as the bundle needs: FrameworkBundle, DoctrineBundle on SQLite — or on the server
 * `PHIL_FEATURE_FLAGS_TEST_DATABASE_URL` names —, TwigBundle, the database store, the admin under
 * `/admin/flags`, and controllers of its own behind flags. The tenant comes from `X-Tenant`, the project from
 * `X-Project`, the operator from `X-Operator`, and only the operator `operator` may change a flag.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public static function databaseUrl(): string
    {
        $url = getenv('PHIL_FEATURE_FLAGS_TEST_DATABASE_URL');

        return \is_string($url) && '' !== $url ? $url : 'sqlite:///%kernel.cache_dir%/flags.sqlite';
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        // One container per database: the URL is compiled into it.
        return sys_get_temp_dir().'/phil-feature-flags-bundle-test/'.hash('xxh128', __DIR__.self::databaseUrl()).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-feature-flags-bundle-test/'.hash('xxh128', __DIR__.self::databaseUrl()).'/log';
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

        // The 403 and 404 responses the tests ask for are logged; the default logger would print them.
        $container->services()->set('logger', NullLogger::class);

        $container->extension('doctrine', [
            'dbal' => ['url' => self::databaseUrl()],
            'orm' => [],
        ]);

        $container->extension('twig', [
            'default_path' => __DIR__.'/templates',
            'strict_variables' => true,
        ]);

        $services = $container->services();
        $services->set(HeaderContextResolver::class)->args([service('request_stack')]);
        $services->set(HeaderActorProvider::class)->args([service('request_stack')]);
        $services->set(ListAuthorizer::class)->args([['operator']]);
        $services->set(RecordingAuditor::class)->public();
        $services->set(RecordingExposures::class)->public();
        $services->set(TestController::class)->args([service('twig')])->tag('controller.service_arguments')->public();

        $container->extension('phil_feature_flags', [
            'context_resolver' => HeaderContextResolver::class,
            'doctrine' => true,
            'exposure_recorder' => RecordingExposures::class,
            'toggle' => ['authorizer' => ListAuthorizer::class, 'auditor' => RecordingAuditor::class],
            'admin' => ['actor_provider' => HeaderActorProvider::class],
            'flags' => [
                'new-checkout' => [
                    'active' => true,
                    'rules' => [['attribute' => 'tenant', 'members' => ['tenant-a']]],
                    'description' => 'The one-page checkout.',
                    'owner' => 'checkout-team',
                    'kind' => 'release',
                ],
                'checkout-layout' => [
                    'active' => true,
                    'experiment' => ['attribute' => 'tenant', 'variants' => ['one-column' => 50, 'two-columns' => 50]],
                    'kind' => 'experiment',
                ],
                'project-beta' => [
                    'active' => true,
                    'rules' => [['attribute' => 'project', 'members' => ['beta-project']]],
                ],
                'forgotten' => ['active' => false, 'expires_on' => '2020-01-31'],
            ],
        ]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@PhilFeatureFlagsBundle/config/routes/admin.php')->prefix('/admin/flags');
        $routes->add('checkout', '/checkout')->controller([TestController::class, 'checkout']);
        $routes->add('two_columns', '/layout/two-columns')->controller([TestController::class, 'twoColumns']);
        $routes->add('page', '/page')->controller([TestController::class, 'page']);
        $routes->add('feature_beta', '/feature')
            ->controller([TestController::class, 'beta'])
            ->condition("service('feature_flags').enabled('project-beta')");
        $routes->add('feature_current', '/feature')->controller([TestController::class, 'current']);
        $routes->add('targeted_beta', '/targeted')
            ->controller([TestController::class, 'beta'])
            ->condition("service('feature_flags').enabled('new-checkout')");
        $routes->add('targeted_current', '/targeted')->controller([TestController::class, 'current']);
    }
}
