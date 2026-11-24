<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the archive needs: FrameworkBundle, DoctrineBundle on SQLite, TwigBundle with strict
 * variables, SecurityBundle, the newsletter of the site `example.com`, and the archive under /newsletter/archive.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $archive the configuration of the archive
     */
    public function __construct(
        private readonly array $archive = [],
    ) {
        parent::__construct('test', true);
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return $this->root().'/cache/'.hash('xxh128', serialize($this->archive));
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->root().'/log';
    }

    private function root(): string
    {
        return sys_get_temp_dir().'/phil-newsletter-archive-test/'.hash('xxh128', __DIR__);
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'a-test-secret-of-enough-bytes',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'default_locale' => 'en',
            'translator' => ['fallbacks' => ['en']],
            'router' => ['default_uri' => 'https://example.com'],
            'csrf_protection' => true,
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'mailer' => ['dsn' => 'null://null'],
            'messenger' => ['transports' => ['async' => 'in-memory://']],
            'scheduler' => null,
            'rate_limiter' => null,
            'cache' => ['app' => 'cache.adapter.array'],
        ]);
        $container->services()->set('logger', NullLogger::class);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///%kernel.cache_dir%/archive.sqlite']]);
        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('security', ['firewalls' => ['main' => ['security' => false]]]);
        $container->extension('phil_newsletter', [
            'check_mail_domain' => false,
            'sites' => ['default' => ['base_url' => 'https://example.com']],
            'profiles' => ['default' => ['from' => 'news@example.com', 'from_name' => 'Example']],
        ]);
        $container->extension('phil_newsletter_archive', $this->archive);
        $container->services()->alias('test.connection', 'doctrine.dbal.default_connection')->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@PhilNewsletterBundle/config/routes.php')->prefix('/newsletter');
        $routes->import('@PhilNewsletterArchiveBundle/config/routes.php')->prefix('/newsletter/archive');
    }
}
