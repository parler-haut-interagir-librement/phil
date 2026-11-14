<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Functional\App;

use Override;
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
 * English, DoctrineBundle on SQLite, TwigBundle with the components, the settings bundle in memory, the user named by
 * the `X-User` header, a flow of the configuration with a step done by an event, a step of a checker declared by
 * attribute, a flow with a question whose answers the application grants, the recipe `Button` of the kit, and the
 * endpoints under `/onboarding`.
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
        return sys_get_temp_dir().'/phil-onboarding-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getShareDir(): string
    {
        return sys_get_temp_dir().'/phil-onboarding-bundle-test/'.hash('xxh128', __DIR__).'/share';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-onboarding-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    /**
     * The recipe `Button` of the kit, where the Toolkit would copy it: the kit beside this package in the monorepo, else
     * among the development requirements of the monorepo's root, else among those of the package alone.
     */
    private function installRecipes(): string
    {
        $root = sys_get_temp_dir().'/phil-onboarding-bundle-test/'.hash('xxh128', __DIR__).'/templates';
        $target = $root.'/components';

        if (!is_dir($target)) {
            mkdir($target, recursive: true);
        }

        $kit = array_find(
            [__DIR__.'/../../../../ux-kit', __DIR__.'/../../../../../vendor/phil/ux-kit'],
            static fn (string $directory): bool => is_file($directory.'/manifest.json'),
        ) ?? __DIR__.'/../../../vendor/phil/ux-kit';

        if (!copy($kit.'/button/templates/components/Button.html.twig', $target.'/Button.html.twig')) {
            throw new RuntimeException(\sprintf('The recipe Button of the kit cannot be copied from %s.', $kit));
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
            'form' => ['csrf_protection' => true],
            'translator' => ['fallbacks' => ['en']],
            'default_locale' => 'en',
        ]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:']]);
        $container->extension('twig', ['strict_variables' => true, 'default_path' => __DIR__.'/templates', 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('phil_settings', ['store' => 'memory', 'current_user' => HeaderUser::class]);
        $container->extension('phil_onboarding', [
            'current_user' => HeaderUser::class,
            'answer_grants' => RecordedGrants::class,
            'flows' => [
                'author' => [
                    'title' => 'Welcome, author',
                    'steps' => [
                        'profile' => ['title' => 'Fill in your profile', 'route' => 'page', 'target' => '#profile'],
                        'first-page' => ['title' => 'Write a first page', 'completion' => 'event', 'event' => PageWritten::class, 'skippable' => false],
                    ],
                ],
                'member' => [
                    'title' => 'Welcome, member',
                    'steps' => [
                        'topics' => ['title' => 'Which topics interest you?', 'completion' => 'question', 'question' => [
                            'multiple' => true,
                            'choices' => [
                                'news' => ['label' => 'News', 'subscriptions' => ['newsletter.news']],
                                'events' => ['label' => 'Events', 'roles' => ['ROLE_EVENTS'], 'subscriptions' => ['newsletter.events']],
                            ],
                        ]],
                    ],
                ],
            ],
        ]);

        $services = $container->services()->defaults()->autowire();
        $services->set('logger', NullLogger::class);
        // An application requires FrameworkBundle, and with it the event dispatcher, in production; installed on its
        // own, the bundle has them in development only, which the container takes as absent.
        $services->set('event_dispatcher', EventDispatcher::class)->public()->tag('event_dispatcher.dispatcher', ['name' => 'event_dispatcher']);
        $services->alias(EventDispatcherInterface::class, 'event_dispatcher');
        $services->alias(PsrEventDispatcherInterface::class, 'event_dispatcher');
        $services->set('clock', MockClock::class)->args(['2026-11-08 10:00:00 UTC'])->public();
        $services->set(HeaderUser::class)->args([new Reference('request_stack')]);
        $services->set(AvatarChecker::class)->autoconfigure()->public();
        $services->set(RecordedGrants::class)->public();
        $services->set(PageController::class)->tag('controller.service_arguments')->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 3).'/config/routes.php')->prefix('/onboarding');
        $routes->add('page', '/page')->controller(PageController::class);
    }
}
