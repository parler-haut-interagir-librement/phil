<?php

declare(strict_types=1);

namespace Phil\OpenBadgesBundle\Tests\Functional\App;

use Override;
use Phil\OpenBadges\Contract\VerificationMethods;
use Phil\OpenBadges\CredentialIssuer;
use Phil\OpenBadges\Status\StatusListIssuer;
use Phil\OpenBadges\Verification\CredentialVerifier;
use Phil\OpenBadgesBundle\Application\Contract\StatusLists;
use Phil\OpenBadgesBundle\Application\Securing;
use Phil\OpenBadgesBundle\Infrastructure\Routing\StatusListUrls;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle, TwigBundle, an issuer whose key is a fixed seed, its
 * status lists in memory, and the bundle's routes under `/open-badges`. The environment `jwt` secures with JSON Web
 * Tokens, the others with Data Integrity proofs; the environment `limited` adds phil/rate-limit-bundle, and counts the
 * checks of the verification page against a limiter of two a minute, kept in a pool the test clears, and reads the
 * profiles of other issuers, of one trusted issuer only.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public const string SEED = "\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1\1";

    /**
     * The environments of the tests, named so that each container's build writes the same `config/reference.php`:
     * with each naming only its own, the builds rewrote it in turn, and the debug container of one went stale at every
     * boot of another.
     *
     * @return list<string>
     */
    private function getAllowedEnvs(): array
    {
        return ['jwt', 'limited', 'test'];
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-open-badges-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-open-badges-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => ['default_uri' => 'https://example.com'],
            'translator' => ['default_path' => '%kernel.project_dir%/translations', 'fallbacks' => ['en']],
        ]);
        $container->extension('twig', ['default_path' => '%kernel.project_dir%/templates']);
        $container->extension('phil_open_badges', [
            'issuer' => ['id' => 'https://example.com/open-badges/issuer', 'name' => 'Example', 'url' => 'https://example.com'],
            'key' => ['secret' => self::SEED],
            'securing' => 'jwt' === $this->environment ? 'jwt' : 'data_integrity',
            'trusted_keys' => ['did:web:partner.example#key-1' => 'z6MkrJVnaZkeFzdQyMZu1cgjg7k1pZZ6pvBQ7XJPt4swbTQ2'],
            'verification' => ['rate_limit' => 'limited' === $this->environment ? 'badge_checks' : null],
            'remote_issuers' => ['enabled' => 'limited' === $this->environment, 'trusted' => ['https://partner.example/issuer']],
        ]);

        if ('limited' === $this->environment) {
            $container->extension('framework', ['cache' => ['pools' => ['cache.badge_checks' => ['adapter' => 'cache.adapter.filesystem', 'public' => true]]]]);
            $container->extension('phil_rate_limit', [
                'cache_pool' => 'cache.badge_checks',
                'limiters' => ['badge_checks' => ['policy' => 'fixed_window', 'limit' => 2, 'interval' => '1 minute']],
            ]);
        }

        $services = $container->services()->defaults()->autowire()->autoconfigure()->public();
        $services->set(InMemoryStatusLists::class);
        $services->alias(StatusLists::class, InMemoryStatusLists::class);

        // The services an application injects, kept public for the tests to reach.
        foreach ([CredentialIssuer::class, CredentialVerifier::class, Securing::class, StatusListIssuer::class, StatusListUrls::class, VerificationMethods::class] as $id) {
            $services->alias('test.'.$id, $id)->public();
        }

        $services->set('logger', NullLogger::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@PhilOpenBadgesBundle/config/routes.php')->prefix('/open-badges');
        $routes->import('@PhilOpenBadgesBundle/config/verification_routes.php')->prefix('/open-badges');
    }
}
