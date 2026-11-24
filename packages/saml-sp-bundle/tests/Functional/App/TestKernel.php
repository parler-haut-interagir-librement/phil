<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Tests\Functional\App;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Override;
use Phil\SamlSpBundle\Infrastructure\Security\SamlAuthenticator;
use Phil\SamlSpBundle\Tests\Fixtures\Saml;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * An application as small as the bundle needs: FrameworkBundle with sessions in files and a cache pool on the file system, which outlives the reset of the services between requests, SecurityBundle with one
 * firewall that runs the SAML authenticator and Symfony's logout, and the bundle with the identity provider `acme`,
 * shaped as Microsoft Entra ID, its metadata in a file, its routes under `/saml`. `/dashboard` needs a signed-in person.
 * The environment `doctrine` adds a Doctrine DBAL connection `default` on SQLite, as DoctrineBundle names it.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param string|null $directory The directory of the cache, the logs, the metadata file and the SQLite file, absolute;
     *                               one shared by the kernels of this copy of the bundle when null
     */
    public function __construct(string $environment, bool $debug, private readonly ?string $directory = null)
    {
        parent::__construct($environment, $debug);
    }

    /** The environment with a Doctrine DBAL connection `default`, on SQLite. */
    public const string DOCTRINE = 'doctrine';

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return $this->root().'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->root().'/log';
    }

    public function root(): string
    {
        return $this->directory ?? sys_get_temp_dir().'/phil-saml-sp-bundle-test/'.hash('xxh128', __DIR__);
    }

    /**
     * The SQLite file of the connection the environment `doctrine` has.
     */
    public function database(): string
    {
        return $this->root().'/doctrine.sqlite';
    }

    public static function dashboard(TokenStorageInterface $tokens): Response
    {
        $token = $tokens->getToken();

        return new Response($token?->getUserIdentifier().' '.implode(',', $token?->getRoleNames() ?? []));
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        if (!is_dir($this->root())) {
            mkdir($this->root(), 0o777, true);
        }

        file_put_contents($this->root().'/acme.xml', Saml::metadata());

        $container->extension('framework', [
            'secret' => 'a-secret-of-the-test-application',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'router' => ['default_uri' => 'https://app.example.com/'],
            'cache' => ['app' => 'cache.adapter.filesystem'],
        ]);
        $container->extension('security', [
            'providers' => ['saml' => ['id' => 'phil_saml_sp.user_provider']],
            'firewalls' => ['main' => [
                'lazy' => true,
                'provider' => 'saml',
                'custom_authenticators' => [SamlAuthenticator::class],
                'logout' => ['path' => '/logout', 'target' => '/'],
            ]],
            'access_control' => [['path' => '^/dashboard', 'roles' => 'ROLE_USER']],
        ]);
        $container->extension('phil_saml_sp', [
            'signing' => Saml::pemPair('sp'),
            'identity_providers' => [
                'acme' => ['metadata_file' => $this->root().'/acme.xml', 'preset' => 'entra_id', 'group_roles' => ['0f5b7f6e-1111-4c2d-9e3f-000000000010' => 'ROLE_TEACHER']],
                'portal' => ['metadata_xml' => Saml::metadata(false), 'allow_unsolicited' => true, 'single_logout' => false],
            ],
        ]);

        if (self::DOCTRINE === $this->environment) {
            $container->services()->set('doctrine.dbal.default_connection', Connection::class)
                ->factory(DriverManager::class.'::getConnection')
                ->args([['driver' => 'pdo_sqlite', 'path' => $this->database()]])
                ->public();
        }

        $container->services()->set('logger', NullLogger::class);
        $container->services()->set(Refusals::class)->autoconfigure()->public();
        $container->services()->set(self::class.'::dashboard')->synthetic();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 3).'/config/routes.php')->prefix('/saml');
        $routes->add('dashboard', '/dashboard')->controller([self::class, 'dashboard']);
        $routes->add('logout', '/logout');
        $routes->add('home', '/')->controller([self::class, 'dashboard']);
    }
}
