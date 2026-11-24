<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Tests\Functional\App;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
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
 * An application as small as the bundle needs: FrameworkBundle with a router, a translator and CSRF protection,
 * DoctrineBundle with the ORM on a SQLite file of the kernel's own — so that a request reads what an earlier one
 * wrote —, Twig with twig/extra-bundle, and the bundle with its routes under /survey, a member named by a header and
 * redirects allowed to https://example.org.
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

    private function root(): string
    {
        return sys_get_temp_dir().'/phil-survey-bundle-test/'.hash('xxh128', __DIR__);
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
        $container->extension('doctrine', ['dbal' => ['driver' => 'pdo_sqlite', 'path' => $this->root().'/survey.sqlite'], 'orm' => []]);
        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('phil_survey', ['redirect_origins' => ['https://example.org'], 'respondent' => HeaderMember::class]);

        $services = $container->services()->defaults()->autowire();
        $services->set('logger', NullLogger::class);
        // An application requires FrameworkBundle, and with it the event dispatcher, in production; installed on its
        // own, the bundle has them in development only, which the container takes as absent.
        $services->set('event_dispatcher', EventDispatcher::class)->public()->tag('event_dispatcher.dispatcher', ['name' => 'event_dispatcher']);
        $services->alias(EventDispatcherInterface::class, 'event_dispatcher');
        $services->alias(PsrEventDispatcherInterface::class, 'event_dispatcher');
        $services->set('clock', MockClock::class)->args(['2026-11-21 19:30:00 UTC'])->public();
        $services->set(HeaderMember::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 3).'/config/routes.php')->prefix('/survey');
    }
}
