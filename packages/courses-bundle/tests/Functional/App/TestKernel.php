<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Functional\App;

use Override;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalLearningRecordStore;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * An application as small as the bundle needs: FrameworkBundle with its CSRF protection and the translator in
 * English, DoctrineBundle on SQLite, TwigBundle with the components and the recipes `Button`, `Badge`, `Progress` and
 * `Textarea` of the kit, the learner named by the `X-User` header, a site at http://localhost whose content origin is
 * http://content.localhost, the pages under `/courses`, the cmi5 endpoints under `/cmi5` and the content under
 * `/content`.
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
        return $this->base().'/cache';
    }

    #[Override]
    public function getShareDir(): string
    {
        return $this->base().'/share';
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->base().'/log';
    }

    public function storage(): string
    {
        return $this->base().'/content';
    }

    private function base(): string
    {
        return sys_get_temp_dir().'/phil-courses-bundle-test/'.hash('xxh128', __DIR__);
    }

    /**
     * The recipes of the kit, where the Toolkit would copy them: the kit of the monorepo of the showcase, else among the
     * development requirements of the monorepo's root, else among those of the package alone.
     */
    private function installRecipes(): string
    {
        $root = $this->base().'/templates';
        $target = $root.'/components';

        if (!is_dir($target)) {
            mkdir($target, recursive: true);
        }

        $kit = array_find(
            [__DIR__.'/../../../../../../phil/packages/ux-kit', __DIR__.'/../../../../../vendor/phil/ux-kit'],
            static fn (string $directory): bool => is_file($directory.'/manifest.json'),
        ) ?? __DIR__.'/../../../vendor/phil/ux-kit';

        foreach (['button' => 'Button', 'badge' => 'Badge', 'progress' => 'Progress', 'textarea' => 'Textarea'] as $directory => $recipe) {
            if (!copy($kit.'/'.$directory.'/templates/components/'.$recipe.'.html.twig', $target.'/'.$recipe.'.html.twig')) {
                throw new RuntimeException(\sprintf('The recipe %s of the kit cannot be copied from %s.', $recipe, $kit));
            }
        }

        return $root;
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'a test secret of sixteen bytes',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'csrf_protection' => true,
            'translator' => ['fallbacks' => ['en']],
            'default_locale' => 'en',
            // The locale of a request read from its Accept-Language, for the course of the learner's language.
            'enabled_locales' => ['en', 'fr'],
            'set_locale_from_accept_language' => true,
        ]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:']]);
        $container->extension('twig', ['strict_variables' => true, 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('phil_courses', [
            'timezone' => 'America/Toronto',
            'current_learner' => HeaderLearner::class,
            'storage' => ['directory' => $this->storage()],
            'content' => ['base_url' => 'http://content.localhost/content', 'allow_localhost' => true],
            'cmi5' => ['actor_home_page' => 'http://localhost/', 'allowed_origins' => ['https://units.example.net'], 'lrs' => SwitchableStore::class],
        ]);

        $services = $container->services()->defaults()->autowire();
        $services->set('logger', NullLogger::class);
        // An application requires FrameworkBundle, and with it the event dispatcher, in production; installed on its
        // own, the bundle has them in development only, which the container takes as absent.
        $services->set('event_dispatcher', EventDispatcher::class)->public()->tag('event_dispatcher.dispatcher', ['name' => 'event_dispatcher']);
        $services->alias(EventDispatcherInterface::class, 'event_dispatcher');
        $services->alias(PsrEventDispatcherInterface::class, 'event_dispatcher');
        $services->set('clock', MockClock::class)->args(['2026-11-13 10:00:00 UTC'])->public();
        $services->set(HeaderLearner::class)->args([new Reference('request_stack')]);
        $services->set('security.authorization_checker', HeaderAuthorization::class)->args([new Reference('request_stack')]);
        $services->set(SwitchableStore::class)->args([new Reference(DbalLearningRecordStore::class)])->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 3).'/config/routes.php')->prefix('/courses');
        $routes->import(\dirname(__DIR__, 3).'/config/cmi5_routes.php')->prefix('/cmi5');
        $routes->import(\dirname(__DIR__, 3).'/config/content_routes.php')->prefix('/content');
        $routes->import(\dirname(__DIR__, 3).'/config/admin_routes.php')->prefix('/admin/courses');
    }
}
