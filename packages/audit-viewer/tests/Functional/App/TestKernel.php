<?php

declare(strict_types=1);

namespace Phil\AuditViewer\Tests\Functional\App;

use Override;
use Phil\AuditBundle\Application\Contract\PayloadReader;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An EasyAdmin back office as small as the viewer needs: FrameworkBundle with sessions and CSRF, SecurityBundle with
 * one session firewall over the accounts of `Auditors`, TwigBundle with the Twig components and the Twig extras
 * EasyAdmin's layout uses, DoctrineBundle on an SQLite file, EasyAdmin with the dashboard `AdminDashboard` under
 * `/admin` and the CRUD pages of an audited order and of a note that is not, the audit bundle with a JSON serialization
 * and its reader, and the viewer, its pages two rows long and its exports three, in the time zone of Toronto, the
 * history of a record open to clerks.
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
        return sys_get_temp_dir().'/phil-audit-viewer-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getShareDir(): string
    {
        return sys_get_temp_dir().'/phil-audit-viewer-test/'.hash('xxh128', __DIR__).'/share';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-audit-viewer-test/'.hash('xxh128', __DIR__).'/log';
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
            'csrf_protection' => true,
            'translator' => ['fallbacks' => ['en']],
            'default_locale' => 'en',
        ]);
        $container->extension('security', [
            'providers' => ['auditors' => ['id' => Auditors::class]],
            'firewalls' => ['main' => ['provider' => 'auditors', 'lazy' => true, 'logout' => true]],
        ]);
        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components']);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///%kernel.cache_dir%/audit.sqlite'], 'orm' => ['mappings' => ['App' => ['type' => 'attribute', 'dir' => __DIR__.'/Entity', 'prefix' => 'Phil\\AuditViewer\\Tests\\Functional\\App\\Entity', 'is_bundle' => false]]]]);
        $container->extension('phil_audit', ['serializer' => JsonSerializer::class]);
        $container->extension('phil_audit_viewer', ['export_role' => 'ROLE_AUDIT_EXPORT', 'history_role' => 'ROLE_CLERK', 'page_size' => 2, 'timezone' => 'America/Toronto', 'export_limit' => 3]);

        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->set('clock', MockClock::class)->args(['2026-11-08 10:00:00 UTC'])->public();
        $services->set(Auditors::class);
        $services->set(JsonSerializer::class);
        $services->set(JsonPayloadReader::class);
        $services->alias(PayloadReader::class, JsonPayloadReader::class);
        $services->set(AdminDashboard::class)->autowire()->autoconfigure();
        $services->set(ShopOrderCrudController::class)->autowire()->autoconfigure();
        $services->set(NoteCrudController::class)->autowire()->autoconfigure();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'easyadmin.routes');
    }
}
