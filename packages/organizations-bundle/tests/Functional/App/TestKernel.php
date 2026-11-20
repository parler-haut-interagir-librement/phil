<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Functional\App;

use Override;
use Phil\OrganizationsBundle\Infrastructure\Memory\InMemoryOrganizationStore;
use Phil\OrganizationsBundle\Tests\Fixtures\Stage;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * An application as small as the pages of the units need: FrameworkBundle with its CSRF protection and the translator
 * in English, TwigBundle with the components and the recipes `Badge`, `Button`, `Input`, `NativeSelect`, `Table` and
 * `Tree` of the kit, the organizations in memory for the whole test, the user named by the `X-User` header, and the pages under
 * `/admin/organizations`.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    private const array RECIPES = ['badge' => 'Badge', 'button' => 'Button', 'input' => 'Input', 'native-select' => 'NativeSelect', 'table' => 'Table', 'tree' => 'Tree'];

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
        return sys_get_temp_dir().'/phil-organizations-bundle-test/'.hash('xxh128', __DIR__);
    }

    /**
     * The recipes of the kit, where the Toolkit would copy them: the kit of the monorepo, else among the development
     * requirements of the package alone.
     */
    private function installRecipes(): string
    {
        $root = $this->base().'/templates';
        $kit = array_find(
            [__DIR__.'/../../../../ux-kit', __DIR__.'/../../../vendor/phil/ux-kit'],
            static fn (string $directory): bool => is_file($directory.'/manifest.json'),
        ) ?? throw new RuntimeException('The kit is not installed.');

        foreach (array_keys(self::RECIPES) as $directory) {
            self::copy($kit.'/'.$directory.'/templates/components', $root.'/components');
        }

        return $root;
    }

    private static function copy(string $from, string $to): void
    {
        if (!is_dir($to)) {
            mkdir($to, recursive: true);
        }

        $entries = scandir($from);

        foreach (false === $entries ? [] : $entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            if (is_dir($from.'/'.$entry)) {
                self::copy($from.'/'.$entry, $to.'/'.$entry);
            } elseif (!copy($from.'/'.$entry, $to.'/'.$entry)) {
                throw new RuntimeException(\sprintf('The recipe file %s cannot be copied.', $entry));
            }
        }
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
        $container->extension('twig', ['strict_variables' => true, 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('phil_organizations', [
            // Kept across the requests of a test, which reset the services tagged kernel.reset.
            'store' => InMemoryOrganizationStore::class,
            'invitations' => ['hash_key' => Stage::KEY],
            'unit_roles' => ['instructor' => ['permissions' => ['courses.assign']]],
        ]);

        $services = $container->services()->defaults()->autowire();
        $services->set('logger', NullLogger::class);
        // An application requires FrameworkBundle, and with it the event dispatcher, in production; installed on its
        // own, the bundle has them in development only, which the container takes as absent.
        $services->set('event_dispatcher', EventDispatcher::class)->public()->tag('event_dispatcher.dispatcher', ['name' => 'event_dispatcher']);
        $services->alias(EventDispatcherInterface::class, 'event_dispatcher');
        $services->alias(PsrEventDispatcherInterface::class, 'event_dispatcher');
        $services->set('clock', MockClock::class)->args(['2026-11-20 10:00:00 UTC'])->public();
        $services->set('security.token_storage', TokenStorage::class)->public();
        $services->set(InMemoryOrganizationStore::class)->public();
        $services->set(HeaderUser::class)->args([new Reference('security.token_storage')])->tag('kernel.event_listener', ['event' => KernelEvents::REQUEST, 'priority' => 8]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 3).'/config/routes/units.php')->prefix('/admin/organizations');
    }
}
