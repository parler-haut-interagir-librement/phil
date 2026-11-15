<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Functional\BackOffice;

use FilesystemIterator;
use Override;
use Phil\ModerationBundle\Application\Contract\CurrentMember;
use Phil\ModerationBundle\Tests\Functional\App\Members;
use Phil\ModerationBundle\Tests\Functional\App\Posts;
use Phil\ModerationBundle\Tests\Functional\App\TestKernel;
use Psr\Log\NullLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An EasyAdmin back office as small as the pages of the moderation need: FrameworkBundle with sessions and CSRF,
 * SecurityBundle with one session firewall over accounts in memory, TwigBundle with the Twig components, the Twig extras
 * EasyAdmin's layout uses and the recipes of the kit, DoctrineBundle on an SQLite file, EasyAdmin with the dashboard
 * `Dashboard` under `/admin`, and the bundle with the forum posts of the test application.
 */
final class BackOfficeKernel extends Kernel
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
        return $this->root().'/cache';
    }

    #[Override]
    public function getShareDir(): string
    {
        return $this->root().'/share';
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->root().'/log';
    }

    private function root(): string
    {
        return sys_get_temp_dir().'/phil-moderation-bundle-test/'.hash('xxh128', __DIR__);
    }

    private function installRecipes(): string
    {
        $target = $this->root().'/templates/components';
        $kit = array_find(
            [__DIR__.'/../../../../ux-kit', __DIR__.'/../../../../../vendor/phil/ux-kit'],
            static fn (string $directory): bool => is_file($directory.'/manifest.json'),
        ) ?? __DIR__.'/../../../vendor/phil/ux-kit';

        foreach (TestKernel::RECIPES as $recipe) {
            $source = $kit.'/'.$recipe.'/templates/components';

            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)) as $file) {
                \assert($file instanceof SplFileInfo);
                $copy = $target.substr($file->getPathname(), \strlen($source));

                if (!is_dir(\dirname($copy))) {
                    mkdir(\dirname($copy), recursive: true);
                }

                copy($file->getPathname(), $copy);
            }
        }

        return $this->root().'/templates';
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
                'mod-1' => ['roles' => ['ROLE_MODERATOR', 'ROLE_ADMIN']],
                'm-1' => ['roles' => ['ROLE_USER']],
            ]]]],
            'firewalls' => ['main' => ['provider' => 'people', 'lazy' => true, 'logout' => true]],
        ]);
        $container->extension('twig', ['strict_variables' => true, 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components']);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///%kernel.cache_dir%/moderation.sqlite'], 'orm' => []]);
        $container->extension('phil_moderation', ['kit_subdirectory' => '']);

        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->set(Dashboard::class)->autowire()->autoconfigure();
        $services->set(Members::class)->args([new Reference('security.token_storage')]);
        $services->alias(CurrentMember::class, Members::class);
        $services->set(Posts::class)->args(['%kernel.cache_dir%/actions.log'])->autoconfigure();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'easyadmin.routes');
    }
}
