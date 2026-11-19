<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the help desk needs: FrameworkBundle with its CSRF protection, Symfony Security with
 * members in memory — a requester, two agents of billing, an agent of both queues —, DoctrineBundle on SQLite, the
 * KeyManagement component with a local key, TwigBundle with the components and the recipes of the kit, an EasyAdmin
 * dashboard, the conversations, and the pages of the requesters under `/help` and of the agents under `/desk`.
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
        return sys_get_temp_dir().'/phil-helpdesk-bundle-test/'.hash('xxh128', __DIR__);
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

        foreach (['button' => 'Button', 'badge' => 'Badge', 'textarea' => 'Textarea', 'input' => 'Input', 'native-select' => 'NativeSelect'] as $directory => $recipe) {
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
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'csrf_protection' => true,
            'translator' => ['fallbacks' => ['en']],
            'default_locale' => 'en',
        ]);
        $container->extension('security', [
            'providers' => ['people' => ['memory' => ['users' => [
                'ada' => ['roles' => ['ROLE_USER']],
                'agent-1' => ['roles' => ['ROLE_BILLING']],
                'agent-2' => ['roles' => ['ROLE_BILLING']],
                'lead' => ['roles' => ['ROLE_BILLING', 'ROLE_TECH']],
            ]]]],
            'firewalls' => ['main' => ['provider' => 'people', 'lazy' => true, 'logout' => true]],
        ]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///%kernel.cache_dir%/helpdesk.sqlite'], 'orm' => []]);
        $container->extension('key_management', ['clients' => ['local' => 'sodium://?keys[k2026]='.rtrim(strtr(base64_encode(str_repeat('k', 32)), '+/', '-_'), '=')]]);
        $container->extension('twig', ['strict_variables' => true, 'default_path' => __DIR__.'/templates', 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('phil_conversation', ['encryption' => ['key' => 'k2026']]);
        $container->extension('phil_helpdesk', [
            'queues' => [
                'billing' => ['label' => 'Billing', 'roles' => ['ROLE_BILLING']],
                'tech' => ['label' => 'Technical', 'roles' => ['ROLE_TECH']],
            ],
            'canned_replies' => ['thanks' => ['label' => 'Thanks for your patience', 'template' => 'canned/thanks.txt.twig']],
        ]);

        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->set('clock', MockClock::class)->args(['2026-11-13 10:00:00 UTC'])->public();
        $services->set(Dashboard::class)->autowire()->autoconfigure();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 3).'/config/routes.php')->prefix('/help');
        $routes->import(\dirname(__DIR__, 3).'/config/routes_agents.php')->prefix('/desk');
        $routes->import('.', 'easyadmin.routes');
    }
}
