<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Functional\App;

use Override;
use Phil\ContentBundle\Infrastructure\Http\ArchiveController;
use Phil\ContentBundle\Infrastructure\Http\ContentController;
use Phil\ContentBundle\Infrastructure\Http\FeedController;
use Phil\ContentBundle\Tests\Functional\App\Entity\News;
use Phil\ContentBundle\Tests\Functional\App\Entity\NewsIdType;
use Phil\ContentBundle\Tests\Functional\App\Entity\StaticPage;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle, DoctrineBundle on SQLite — or on the server
 * `PHIL_CONTENT_TEST_DATABASE_URL` names, to run the same requests and commands on MySQL or PostgreSQL —, TwigBundle
 * (`config/bundles.php`), and two kinds of content mounted under `/{_locale}/news/{slug}` and `/{_locale}/{slug}`, the
 * second also on a route of its own, `/{_locale}/about`, with its slug as a default.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * The database the kernel runs on: a SQLite file of its own unless the environment names a server.
     *
     *     PHIL_CONTENT_TEST_DATABASE_URL='mysql://root:secret@127.0.0.1:3306/probe?serverVersion=8.0' vendor/bin/phpunit tests/Functional
     */
    public static function databaseUrl(): string
    {
        $url = getenv('PHIL_CONTENT_TEST_DATABASE_URL');

        return \is_string($url) && '' !== $url ? $url : 'sqlite:///%kernel.cache_dir%/content.sqlite';
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        // One container per database: the URL is compiled into it.
        return sys_get_temp_dir().'/phil-content-bundle-test/'.hash('xxh128', __DIR__.self::databaseUrl()).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-content-bundle-test/'.hash('xxh128', __DIR__.self::databaseUrl()).'/log';
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
            // The registry the publication workflow is added to, as a scheduler finds it.
            'workflows' => true,
            'csrf_protection' => true,
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
        ]);
        // The administration behind a firewall of two editors in memory, signed in by the tests.
        $container->extension('security', [
            'providers' => ['editors' => ['memory' => ['users' => [
                'alice' => ['password' => 'unused', 'roles' => ['ROLE_EDITOR']],
                'bob' => ['password' => 'unused', 'roles' => ['ROLE_EDITOR']],
                'reader' => ['password' => 'unused', 'roles' => ['ROLE_USER']],
            ]]]],
            'firewalls' => ['main' => ['lazy' => true, 'provider' => 'editors']],
        ]);
        // The 404 responses the tests ask for are logged as warnings; the default logger would print them.
        $container->services()->set('logger', NullLogger::class);
        $container->services()->set(PageEventRecorder::class)->autoconfigure()->public();
        $container->extension('doctrine', [
            'dbal' => [
                'url' => self::databaseUrl(),
                'types' => [NewsIdType::NAME => NewsIdType::class],
            ],
            'orm' => [
                'mappings' => [
                    'App' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => __DIR__.'/Entity',
                        'prefix' => 'Phil\ContentBundle\Tests\Functional\App\Entity',
                    ],
                ],
            ],
        ]);
        $container->extension('twig', [
            'default_path' => __DIR__.'/templates',
            'strict_variables' => true,
        ]);
        $container->extension('phil_content', [
            'locales' => ['fr' => [], 'fr_CA' => ['fr'], 'en' => []],
            'roots' => [News::class, StaticPage::class],
            'routes' => ['news' => 'news_show', 'static_page' => ['page_show', 'about']],
            'templates' => ['news' => 'news.html.twig'],
            'default_template' => 'page.html.twig',
            'archives' => ['news'],
            'admin' => ['enabled' => true, 'role' => 'ROLE_EDITOR', 'timezone' => 'America/Montreal'],
        ]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/../../../config/routes.php');
        $routes->import(__DIR__.'/../../../config/routes_admin.php')->prefix('/admin/contents');
        $routes->add('news_feed', '/{_locale}/feeds/{resourceKey}.atom')->controller(FeedController::class)->defaults(['title' => 'Nouvelles']);
        $routes->add('news_archive', '/{_locale}/news/{slug}/v{version}')->controller(ArchiveController::class)->defaults(['resourceKey' => 'news'])->requirements(['version' => '\d{1,9}']);
        $routes->add('news_show', '/{_locale}/news/{slug}')->controller(ContentController::class);
        $routes->add('about', '/{_locale}/about')->controller(ContentController::class)->defaults(['slug' => 'a-propos']);
        $routes->add('page_show', '/{_locale}/{slug}')->controller(ContentController::class);
    }
}
