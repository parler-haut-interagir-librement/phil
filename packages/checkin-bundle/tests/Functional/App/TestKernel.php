<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Tests\Functional\App;

use Override;
use Phil\CheckinBundle\Infrastructure\Http\DeskConfig;
use Phil\CheckinBundle\Tests\Unit\Fixture\CheckpointGrants;
use Phil\CheckinBundle\Tests\Unit\Fixture\FixedCredentials;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * An application as small as the bundle needs: FrameworkBundle with a router, its stateless CSRF protection and
 * AssetMapper, DoctrineBundle on SQLite, the bundle with its routes and a rule at the hall, two tickets as resolver and
 * catalog plugged in by autoconfiguration, a security that grants the hall only, and a listener of the scans recorded;
 * {@see self::BROWSER} adapts it to the browser suite.
 *
 * Symfony's `ServicesBundle` wires the event dispatcher only when `ContainerBuilder::willBeAvailable()` says so, and
 * it does not when `symfony/event-dispatcher` is installed for development only — the bundle installed on its own,
 * with FrameworkBundle in its `require-dev`. The kernel declares the dispatcher itself, which the one of
 * `ServicesBundle` replaces whenever it is there, so that the suite boots both from the monorepo and from the bundle
 * installed alone.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * The environment of the browser suite: the server's real clock, which the browser's agrees with, an SQLite file
     * that outlives each request of PHP's built-in server, and no rule at the hall.
     */
    public const string BROWSER = 'browser';

    /**
     * The environment whose hall rule reads a variable a rule does not have, for the expression lint.
     */
    public const string BROKEN_RULE = 'broken_rule';

    /** The rule of the hall in the broken environment: `holder` is not a variable of the rules. */
    public const string BROKEN = 'holder.age >= 18';

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-checkin-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-checkin-bundle-test/'.hash('xxh128', __DIR__).'/log';
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
            'csrf_protection' => true,
        ]);
        $browser = self::BROWSER === $this->environment;
        $container->extension('doctrine', ['dbal' => ['url' => $browser ? 'sqlite:///'.$this->getCacheDir().'/checkin.sqlite' : 'sqlite:///:memory:']]);
        // No catalog named: the tickets, a resolver and a catalog, plug in by autoconfiguration, as a module would.
        $rules = match ($this->environment) {
            self::BROWSER => [],
            self::BROKEN_RULE => ['hall' => self::BROKEN],
            default => ['hall' => 'credential.label != null'],
        };
        $container->extension('phil_checkin', ['enabled' => true, 'offline' => ['min_code_bits' => 0], 'rules' => ['checkpoints' => $rules]]);

        $services = $container->services();
        $services->set('event_dispatcher', EventDispatcher::class)->public()->tag('container.hot_path');
        $services->alias(EventDispatcherInterface::class, 'event_dispatcher');
        $services->alias(PsrEventDispatcherInterface::class, 'event_dispatcher');
        $services->set(RecordedScans::class)->public()->tag('kernel.event_listener');
        $services->set('logger', NullLogger::class);
        // The browser suite decides offline on the browser's clock, which only a real clock on the server agrees with.
        $browser ? $services->set('clock', NativeClock::class)->public() : $services->set('clock', MockClock::class)->args(['2026-11-08 10:00:00 UTC'])->public();
        $services->set(FixedCredentials::class)->autoconfigure();
        $services->set('security.authorization_checker', CheckpointGrants::class);
        $services->alias('test.'.DeskConfig::class, DeskConfig::class)->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 3).'/config/routes.php')->prefix('/_checkin');
    }
}
