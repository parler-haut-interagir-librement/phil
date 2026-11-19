<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle, DoctrineBundle on SQLite in memory, SecurityBundle
 * with two users in memory — `user-7`, and `teacher`, a moderator — signed in by the header `X-User`, phil/courses-bundle and phil/privacy-bundle;
 * the bundle with a BigBlueButton server behind a scripted HTTP client and a Jitsi server of its own, its routes under
 * `/vc`, its clock stopped at 1 700 000 000.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public const string SECRET = 'a-secret-of-the-test-application';

    public const string JITSI_SECRET = 'jitsi-application-secret-012345';

    public const string JITSI_HOOKS = 'jitsi-webhook-secret-0123456789';

    public const string BBB_SECRET = 'bbb-shared-secret-of-the-test-01';

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

    private function root(): string
    {
        return sys_get_temp_dir().'/phil-virtual-classroom-bundle-test/'.hash('xxh128', __DIR__);
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => self::SECRET,
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => ['default_uri' => 'https://app.example.com/'],
        ]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:']]);
        $container->extension('security', [
            'providers' => ['users' => ['memory' => ['users' => [
                'user-7' => ['password' => 'x', 'roles' => ['ROLE_USER']],
                'teacher' => ['password' => 'x', 'roles' => ['ROLE_USER', 'ROLE_VIRTUAL_CLASSROOM_MODERATOR']],
            ]]]],
            'firewalls' => ['main' => ['stateless' => true, 'provider' => 'users', 'custom_authenticators' => [HeaderAuthenticator::class]]],
        ]);
        $container->extension('phil_courses', ['storage' => ['directory' => $this->root().'/content']]);
        $container->extension('phil_virtual_classroom', [
            'http_client' => 'test.bbb_client',
            'providers' => [
                'bbb' => ['type' => 'bigbluebutton', 'url' => 'https://bbb.example.com/bigbluebutton/api/', 'secret' => self::BBB_SECRET, 'signed_analytics' => false],
                'jitsi' => ['type' => 'jitsi', 'url' => 'https://meet.example.com', 'app_id' => 'app1', 'secret' => self::JITSI_SECRET, 'webhook_secret' => self::JITSI_HOOKS],
            ],
            'courses' => ['threshold' => 50],
        ]);

        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->set('clock', MockClock::class)->args(['@1700000000'])->public();
        $services->set(ScriptedServer::class)->public();
        $services->set(HeaderAuthenticator::class);
        $services->set('test.bbb_client', MockHttpClient::class)->args([new Reference(ScriptedServer::class)]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 3).'/config/routes.php')->prefix('/vc');
    }
}
