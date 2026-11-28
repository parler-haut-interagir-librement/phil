<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Tests\Functional\App;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\ShiftsBundle\Tests\Fixtures\KnownBirthDates;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * An application as small as the bundle needs: FrameworkBundle with a router, a translator, sessions, CSRF protection,
 * Forms and Messenger, DoctrineBundle with the ORM on a SQLite file of the kernel's own, SecurityBundle with people in
 * memory, Twig with its components, a clock the tests move, and the bundle with its routes under /shifts.
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
        return $this->root().'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->root().'/log';
    }

    /**
     * Creates the tables again from the schema the ORM generates, which the bundle's listener completes.
     */
    public function resetDatabase(): void
    {
        $entityManager = $this->getContainer()->get('doctrine.orm.entity_manager');
        \assert($entityManager instanceof EntityManagerInterface);
        $connection = $entityManager->getConnection();

        foreach ($connection->createSchemaManager()->introspectTableNames() as $table) {
            $connection->executeStatement('DROP TABLE '.$table->toString());
        }

        foreach (new SchemaTool($entityManager)->getSchemaFromMetadata([])->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }
    }

    public function connection(): Connection
    {
        $connection = $this->getContainer()->get('doctrine.dbal.default_connection');
        \assert($connection instanceof Connection);

        return $connection;
    }

    public function clock(): MockClock
    {
        $clock = $this->getContainer()->get('clock');
        \assert($clock instanceof MockClock);

        return $clock;
    }

    private function root(): string
    {
        return sys_get_temp_dir().'/phil-shifts-bundle-test/'.hash('xxh128', __DIR__);
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'a test secret that is long enough',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => ['utf8' => true],
            'csrf_protection' => true,
            'form' => ['enabled' => true, 'csrf_protection' => ['enabled' => true]],
            'messenger' => ['enabled' => true],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'default_locale' => 'en',
            'translator' => ['fallbacks' => ['en']],
        ]);
        $container->extension('doctrine', ['dbal' => ['driver' => 'pdo_sqlite', 'path' => $this->root().'/shifts.sqlite'], 'orm' => []]);
        $container->extension('security', [
            'password_hashers' => [InMemoryUser::class => 'plaintext'],
            'providers' => ['users' => ['memory' => ['users' => [
                'ada' => ['password' => 'x', 'roles' => ['ROLE_USER']],
                'bob' => ['password' => 'x', 'roles' => ['ROLE_USER', 'FIRST_AID']],
                'grace' => ['password' => 'x', 'roles' => ['ROLE_USER', 'ROLE_SHIFTS_COORDINATOR']],
                'kid' => ['password' => 'x', 'roles' => ['ROLE_USER']],
            ]]]],
            'firewalls' => ['main' => ['lazy' => true, 'provider' => 'users']],
        ]);
        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('phil_shifts', [
            'time_zone' => 'America/Toronto',
            'rest_minutes' => 15,
            'minors' => ['birth_dates' => KnownBirthDates::class],
            'absences' => ['threshold' => 1],
        ]);

        $services = $container->services()->defaults()->autowire();
        $services->set('logger', NullLogger::class);
        // An application requires FrameworkBundle, and with it the event dispatcher, in production; installed on its
        // own, the bundle has them in development only, which the container takes as absent.
        $services->set('event_dispatcher', EventDispatcher::class)->public()->tag('event_dispatcher.dispatcher', ['name' => 'event_dispatcher']);
        $services->alias(EventDispatcherInterface::class, 'event_dispatcher');
        $services->alias(PsrEventDispatcherInterface::class, 'event_dispatcher');
        $services->set(KnownBirthDates::class)->args([['kid' => '2012-05-01']]);
        $services->set('clock', MockClock::class)->args(['2026-11-27 10:00:00 UTC'])->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 3).'/config/routes.php')->prefix('/shifts');
        // A page of the application that shows the bundle's components.
        $routes->add('dashboard', '/dashboard')->template('dashboard.html.twig');
    }
}
