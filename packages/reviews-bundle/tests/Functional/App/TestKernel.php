<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Tests\Functional\App;

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
 * English, DoctrineBundle on SQLite, TwigBundle with the components and the recipes `Rating`, `Button`, `Badge`,
 * `Input`, `Checkbox` and `Textarea` of the kit, the member named by the `X-User` header, `shop` answering the reviews,
 * a type `game` rated on two criteria with superlatives, and the pages under `/reviews`.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    private const array RECIPES = ['rating' => 'Rating', 'button' => 'Button', 'badge' => 'Badge', 'input' => 'Input', 'checkbox' => 'Checkbox', 'textarea' => 'Textarea'];

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

    private function base(): string
    {
        return sys_get_temp_dir().'/phil-reviews-bundle-test/'.hash('xxh128', __DIR__);
    }

    /**
     * The recipes of the kit, where the Toolkit would copy them: the kit of the public monorepo, else among the
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

        foreach (self::RECIPES as $directory => $recipe) {
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
        ]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:']]);
        $container->extension('twig', ['strict_variables' => true, 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('phil_reviews', [
            'current_reviewer' => HeaderReviewer::class,
            'responders' => ShopResponds::class,
            'page_size' => 2,
            'rate_limit' => ['write' => ['reviews'], 'vote' => [['limiter' => 'votes', 'key' => 'user']]],
            'types' => [
                'game' => ['criteria' => ['rules', 'replayability'], 'superlatives' => ['gorgeous', 'quick'], 'prior' => ['count' => 2, 'minimum' => 2]],
            ],
        ]);

        $services = $container->services()->defaults()->autowire();
        $services->set('logger', NullLogger::class);
        // An application requires FrameworkBundle, and with it the event dispatcher, in production; installed on its
        // own, the bundle has them in development only, which the container takes as absent.
        $services->set('event_dispatcher', EventDispatcher::class)->public()->tag('event_dispatcher.dispatcher', ['name' => 'event_dispatcher']);
        $services->alias(EventDispatcherInterface::class, 'event_dispatcher');
        $services->alias(PsrEventDispatcherInterface::class, 'event_dispatcher');
        $services->set('clock', MockClock::class)->args(['2026-11-21 10:00:00 UTC'])->public();
        $services->set(HeaderReviewer::class)->args([new Reference('request_stack')]);
        $services->set(ShopResponds::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 3).'/config/routes.php')->prefix('/reviews');
    }
}
