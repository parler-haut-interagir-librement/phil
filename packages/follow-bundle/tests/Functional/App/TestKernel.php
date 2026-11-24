<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Tests\Functional\App;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\FollowBundle\Application\Contract\FollowApproval;
use Phil\FollowBundle\Application\OpenAccess;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * An application as small as the bundle needs: FrameworkBundle with a router and a translator, DoctrineBundle with
 * the ORM on a SQLite file of the kernel's own — so that a request reads what an earlier one wrote —, Twig, the
 * notifications kept in memory, and the bundle with its routes, a member named by a header, the profile of `bob`
 * locked, and mutual relationships turned on.
 */
final class TestKernel extends Kernel implements FollowApproval
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

    #[Override]
    public function approverOf(SubjectReference $subject, string $follower): ?string
    {
        return 'member:bob' === $subject->key() ? 'bob' : null;
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

    private function root(): string
    {
        return sys_get_temp_dir().'/phil-follow-bundle-test/'.hash('xxh128', __DIR__);
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
            'default_locale' => 'en',
            'translator' => ['fallbacks' => ['en']],
        ]);
        $container->extension('doctrine', ['dbal' => ['driver' => 'pdo_sqlite', 'path' => $this->root().'/follow.sqlite'], 'orm' => []]);
        $container->extension('twig', ['strict_variables' => true, 'default_path' => __DIR__.'/templates']);
        $container->extension('phil_notifications', ['store' => 'memory', 'message_bus' => null, 'recipient_directory' => People::class, 'current_recipient' => HeaderMember::class]);
        $container->extension('phil_follow', [
            'access' => OpenAccess::class,
            'approval' => 'kernel',
            'events' => ['reply.created'],
            'mutual' => ['enabled' => true],
        ]);

        $services = $container->services()->defaults()->autowire();
        $services->set('logger', NullLogger::class);
        // An application requires FrameworkBundle, and with it the event dispatcher, in production; installed on its
        // own, the bundle has them in development only, which the container takes as absent.
        $services->set('event_dispatcher', EventDispatcher::class)->public()->tag('event_dispatcher.dispatcher', ['name' => 'event_dispatcher']);
        $services->alias(EventDispatcherInterface::class, 'event_dispatcher');
        $services->alias(PsrEventDispatcherInterface::class, 'event_dispatcher');
        $services->set('clock', MockClock::class)->args(['2026-11-21 12:00:00 UTC'])->public();
        $services->set(HeaderMember::class);
        $services->set(People::class);
        $services->set(PageController::class)->tag('controller.service_arguments')->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 3).'/config/routes.php')->prefix('/follow');
        $routes->add('page', '/page/{subject}/{member}')->controller(PageController::class)->defaults(['subject' => 'thread:1', 'member' => 'bob']);
    }
}
