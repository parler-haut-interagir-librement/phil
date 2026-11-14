<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Functional\App;

use Override;
use Phil\CertificatesBundle\Application\Awarding;
use Phil\CertificatesBundle\Application\CertificateStatusLists;
use Phil\CertificatesBundle\Application\Courses;
use Phil\CertificatesBundle\Application\Revocation;
use Phil\CertificatesBundle\Domain\Contract\CertificateRepository;
use Phil\CertificatesBundle\Domain\Contract\CourseRepository;
use Phil\OpenBadges\Verification\CredentialVerifier;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle with Gotenberg played by its HTTP client, TwigBundle,
 * DoctrineBundle on an in-memory SQLite database, an issuer whose key is a fixed seed, a frozen clock, and the routes
 * of the issuer under `/open-badges` and of the register under `/certificates`.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public const string SEED = "\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1";

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-certificates-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-certificates-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'disallow_search_engine_index' => false,
            'router' => ['default_uri' => 'https://example.com'],
            'translator' => ['default_path' => '%kernel.project_dir%/translations', 'fallbacks' => ['en']],
            'http_client' => ['mock_response_factory' => GotenbergResponses::class],
        ]);
        $container->extension('twig', ['default_path' => '%kernel.project_dir%/templates']);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:'], 'orm' => []]);
        $container->extension('phil_open_badges', [
            'issuer' => ['id' => 'https://example.com/open-badges/issuer', 'name' => 'Example Academy', 'url' => 'https://example.com'],
            'key' => ['secret' => self::SEED],
        ]);
        $container->extension('phil_paged_media', ['gotenberg' => ['url' => 'http://gotenberg:3000']]);
        $container->extension('phil_certificates', ['status_lists' => ['size' => 131_072]]);

        $services = $container->services()->defaults()->autowire()->autoconfigure()->public();
        $services->set(GotenbergResponses::class);
        $services->set(RecordedEvents::class);
        $services->set('clock', MockClock::class)->args(['2026-11-08 10:00:00 UTC']);

        // The services an application injects, kept public for the tests to reach.
        foreach ([Awarding::class, Courses::class, Revocation::class, CertificateStatusLists::class, CertificateRepository::class, CourseRepository::class, CredentialVerifier::class] as $id) {
            $services->alias('test.'.$id, $id)->public();
        }

        $services->set('logger', NullLogger::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@PhilOpenBadgesBundle/config/routes.php')->prefix('/open-badges');
        $routes->import('@PhilCertificatesBundle/config/routes.php')->prefix('/certificates');
    }
}
