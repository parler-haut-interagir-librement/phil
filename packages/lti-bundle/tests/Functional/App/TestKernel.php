<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Tests\Functional\App;

use FilesystemIterator;
use Override;
use Phil\Lti\Contract\KeySetSource;
use Phil\Lti\Contract\KeyStore;
use Phil\Lti\Contract\Roster;
use Phil\Lti\Message\Context;
use Phil\Lti\Message\ContextRole;
use Phil\Lti\Message\Roles;
use Phil\Lti\Platform\Nrps\InMemoryRoster;
use Phil\Lti\Service\Nrps\Member;
use Phil\Lti\Service\Scope;
use Phil\LtiBundle\Application\Contract\LaunchMessages;
use Phil\LtiBundle\Application\Event\DeepLinkingReturned;
use Phil\LtiBundle\Application\Event\LaunchReceived;
use Phil\LtiBundle\Application\Event\LineItemDeleted;
use Phil\LtiBundle\Application\Event\LineItemSaved;
use Phil\LtiBundle\Application\Event\ScoreReceived;
use Phil\LtiBundle\Infrastructure\Routing\PlatformUrls;
use Phil\LtiBundle\Infrastructure\Security\LtiLaunchAuthenticator;
use Phil\LtiBundle\Infrastructure\Security\LtiUserProvider;
use Phil\LtiBundle\Tests\Unit\Fixture\TestKeys;
use Psr\Log\NullLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\User\InMemoryUser;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * An application that is both an LTI platform and a tool registered with itself: FrameworkBundle, DoctrineBundle on
 * SQLite, TwigBundle with the recipe Button of the PHIL UX kit copied as `ux:install` would, Symfony Security with a
 * member signing in by HTTP Basic on the platform and the launch authenticator on the tool, keys from the
 * configuration, and the key sets of the other side read from its own.
 */
final class TestKernel extends Kernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    public const string ISSUER = 'https://lms.example.org';

    public const string CLIENT = 'client-42';

    public function __construct(
        string $environment,
        bool $debug,
        private readonly string $store = 'dbal',
    ) {
        parent::__construct($environment, $debug);
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return $this->root().'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->root().'/log';
    }

    public function course(): Response
    {
        return new Response('course');
    }

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        $container->setAlias(KeySetSource::class, SelfKeySets::class);
    }

    private function root(): string
    {
        return sys_get_temp_dir().'/phil-lti-bundle-test/'.hash('xxh128', __DIR__.$this->store);
    }

    private function installRecipes(): string
    {
        $target = $this->root().'/templates/components/ui';
        $kit = array_find(
            [__DIR__.'/../../../../ux-kit', __DIR__.'/../../../../../vendor/phil/ux-kit'],
            static fn (string $directory): bool => is_file($directory.'/manifest.json'),
        ) ?? __DIR__.'/../../../vendor/phil/ux-kit';
        $source = $kit.'/button/templates/components';

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)) as $file) {
            \assert($file instanceof SplFileInfo);
            $copy = $target.substr($file->getPathname(), \strlen($source));

            if (!is_dir(\dirname($copy))) {
                mkdir(\dirname($copy), recursive: true);
            }

            copy($file->getPathname(), $copy);
        }

        return $this->root().'/templates';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->parameters()->set('.ux_toolkit.components.subdirectory', 'ui');
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => ['utf8' => true],
            'default_locale' => 'en',
            'translator' => ['fallbacks' => ['en']],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
        ]);

        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->set(SelfKeySets::class)->args([service(KeyStore::class)]);
        $services->set(CourseLaunches::class)->args([service(PlatformUrls::class)]);
        $services->alias(LaunchMessages::class, CourseLaunches::class);
        $services->set(InMemoryRoster::class)
            ->call('add', [
                new Definition(Context::class, ['course-1', 'CS101']),
                [self::CLIENT],
                [
                    new Definition(Member::class, ['m1', new Definition(Roles::class, [[ContextRole::Learner->value]])->setFactory([Roles::class, 'of'])]),
                    new Definition(Member::class, ['m2', new Definition(Roles::class, [[ContextRole::Instructor->value]])->setFactory([Roles::class, 'of'])]),
                    new Definition(Member::class, ['m3', new Definition(Roles::class, [[ContextRole::Learner->value]])->setFactory([Roles::class, 'of'])]),
                ],
            ]);
        $services->alias(Roster::class, InMemoryRoster::class)->public();
        $services->set(RecordingListener::class)->public()
            ->tag('kernel.event_listener', ['event' => LaunchReceived::class])
            ->tag('kernel.event_listener', ['event' => DeepLinkingReturned::class])
            ->tag('kernel.event_listener', ['event' => ScoreReceived::class])
            ->tag('kernel.event_listener', ['event' => LineItemSaved::class])
            ->tag('kernel.event_listener', ['event' => LineItemDeleted::class]);

        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///%kernel.cache_dir%/lti.sqlite']]);
        $container->extension('twig', ['strict_variables' => true, 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('security', [
            'password_hashers' => [InMemoryUser::class => 'plaintext'],
            'providers' => [
                'people' => ['memory' => ['users' => ['ada' => ['password' => 'ada', 'roles' => ['ROLE_USER']]]]],
                'lti' => ['id' => LtiUserProvider::class],
            ],
            'firewalls' => [
                'lti_services' => ['pattern' => '^/lti/(jwks|platform/(token|contexts))', 'security' => false],
                'lti_launch' => ['pattern' => '^/lti/(login|launch)', 'provider' => 'lti', 'custom_authenticators' => [LtiLaunchAuthenticator::class]],
                'main' => ['provider' => 'people', 'http_basic' => null],
            ],
        ]);

        $client = [
            'client_id' => self::CLIENT,
            'login_url' => 'https://localhost/lti/login',
            'redirect_urls' => ['https://localhost/lti/launch'],
            'key_set_url' => 'https://localhost/lti/jwks',
            'scopes' => Scope::ALL,
            'deployments' => ['1'],
        ];

        $container->extension('phil_lti', [
            'store' => $this->store,
            'allow_localhost' => true,
            'keys' => ['source' => 'config', 'private_key' => TestKeys::pem(), 'key_id' => 'test-1'],
            'tool' => ['enabled' => true, 'platforms' => [[
                'issuer' => self::ISSUER,
                'client_id' => self::CLIENT,
                'authorization_url' => 'https://localhost/lti/platform/authorize',
                'token_url' => 'https://localhost/lti/platform/token',
                'key_set_url' => 'https://localhost/lti/jwks',
                'deployments' => ['1'],
            ]]],
            'platform' => ['enabled' => true, 'issuer' => self::ISSUER, 'token_url' => 'https://localhost/lti/platform/token', 'tools' => [$client]],
        ]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@PhilLtiBundle/config/routes/jwks.php')->prefix('/lti');
        $routes->import('@PhilLtiBundle/config/routes/tool.php')->prefix('/lti');
        $routes->import('@PhilLtiBundle/config/routes/platform.php')->prefix('/lti/platform');
        $routes->add('course', '/course/{id}')->controller($this->course(...));
    }
}
