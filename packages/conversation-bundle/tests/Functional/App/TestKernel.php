<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Tests\Functional\App;

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
 * English, DoctrineBundle on SQLite, the KeyManagement component with a local key, TwigBundle with the components and
 * the recipes `Button`, `Badge` and `Textarea` of the kit, the participant named by the `X-User` header, and the pages
 * under `/messages`.
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

    private function base(): string
    {
        return sys_get_temp_dir().'/phil-conversation-bundle-test/'.hash('xxh128', __DIR__);
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

        foreach (['button' => 'Button', 'badge' => 'Badge', 'textarea' => 'Textarea'] as $directory => $recipe) {
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
        $container->extension('key_management', ['clients' => ['local' => 'sodium://?keys[k2026]='.rtrim(strtr(base64_encode(str_repeat('k', 32)), '+/', '-_'), '=')]]);
        $container->extension('twig', ['strict_variables' => true, 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('phil_conversation', [
            'encryption' => ['key' => 'k2026'],
            'current_participant' => HeaderParticipant::class,
            'limits' => ['page_size' => 3],
        ]);

        $services = $container->services()->defaults()->autowire();
        $services->set('logger', NullLogger::class);
        // An application requires FrameworkBundle, and with it the event dispatcher, in production; installed on its
        // own, the bundle has them in development only, which the container takes as absent.
        $services->set('event_dispatcher', EventDispatcher::class)->public()->tag('event_dispatcher.dispatcher', ['name' => 'event_dispatcher']);
        $services->alias(EventDispatcherInterface::class, 'event_dispatcher');
        $services->alias(PsrEventDispatcherInterface::class, 'event_dispatcher');
        $services->set('clock', MockClock::class)->args(['2026-11-13 10:00:00 UTC'])->public();
        $services->set(HeaderParticipant::class)->args([new Reference('request_stack')]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 3).'/config/routes.php')->prefix('/messages');
    }
}
