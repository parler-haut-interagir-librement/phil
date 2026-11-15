<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Functional\App;

use FilesystemIterator;
use Override;
use Phil\IncidentMode\Messenger\LiftIncident;
use Phil\ModerationBundle\Application\Contract\CurrentMember;
use Phil\ModerationBundle\Application\Contract\TrustedHiders;
use Psr\Log\NullLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * An application as small as the bundle needs: FrameworkBundle with Messenger on an in-memory transport, DoctrineBundle
 * on SQLite, TwigBundle with the recipes of the kit copied where `ux:install` would put them, Symfony Security with a
 * moderator, an administrator, a moderator of comments, a member and a trusted member signing in by HTTP Basic, the
 * application's own members and trust, a target of forum posts, the incident mode installed, and a page with the
 * report form.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /** The recipes the templates of the bundle call. */
    public const array RECIPES = ['alert', 'badge', 'button', 'field', 'input', 'native-select', 'table', 'textarea'];

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
    public function getLogDir(): string
    {
        return $this->root().'/log';
    }

    public function actionLog(): string
    {
        return $this->getCacheDir().'/actions.log';
    }

    private function root(): string
    {
        return sys_get_temp_dir().'/phil-moderation-bundle-test/'.hash('xxh128', __DIR__);
    }

    /**
     * The templates of the recipes, where the kit's manifests copy them.
     */
    private function installRecipes(): string
    {
        $target = $this->root().'/templates/components/ui';
        $kit = array_find(
            [__DIR__.'/../../../../ux-kit', __DIR__.'/../../../../../vendor/phil/ux-kit'],
            static fn (string $directory): bool => is_file($directory.'/manifest.json'),
        ) ?? __DIR__.'/../../../vendor/phil/ux-kit';

        foreach (self::RECIPES as $recipe) {
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
        $container->parameters()->set('.ux_toolkit.components.subdirectory', 'ui');
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => ['utf8' => true],
            'default_locale' => 'en',
            'translator' => ['fallbacks' => ['en']],
            'csrf_protection' => true,
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'messenger' => [
                'transports' => ['memory' => 'in-memory://'],
                'routing' => [LiftIncident::class => 'memory'],
            ],
        ]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///%kernel.cache_dir%/moderation.sqlite'], 'orm' => []]);
        $container->extension('twig', ['strict_variables' => true, 'default_path' => __DIR__.'/templates', 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('security', [
            'password_hashers' => [InMemoryUser::class => 'plaintext'],
            'providers' => ['people' => ['memory' => ['users' => [
                'mod-1' => ['password' => 'p', 'roles' => ['ROLE_MODERATOR']],
                'admin-1' => ['password' => 'p', 'roles' => ['ROLE_ADMIN']],
                'keeper' => ['password' => 'p', 'roles' => ['ROLE_COMMENTS']],
                'm-1' => ['password' => 'p', 'roles' => ['ROLE_USER']],
                'trusted' => ['password' => 'p', 'roles' => ['ROLE_USER']],
                'author' => ['password' => 'p', 'roles' => ['ROLE_USER']],
            ]]]],
            'firewalls' => ['main' => ['http_basic' => null, 'provider' => 'people', 'stateless' => true]],
        ]);
        $container->extension('phil_moderation', [
            'roles' => [
                ['name' => 'moderator', 'attribute' => 'ROLE_MODERATOR'],
                ['name' => 'administrator', 'attribute' => 'ROLE_ADMIN'],
                ['name' => 'comment-keeper', 'attribute' => 'ROLE_COMMENTS', 'types' => ['comment']],
            ],
            'surge' => ['reports' => 2, 'within' => 3600],
            'sandbox' => ['types' => ['forum.post']],
            'incident' => [
                'presets' => [['name' => 'quiet', 'label' => 'Quiet hours', 'restrictions' => ['new-posts'], 'default_minutes' => 60, 'maximum_minutes' => 240]],
                'without' => ['read-only'],
            ],
            'statements' => [
                'terms_url' => '/terms',
                'out_of_court' => [['name' => 'A settlement body', 'url' => 'https://settlement.example']],
            ],
        ]);

        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->set(Members::class)->args([new Reference('security.token_storage')]);
        $services->alias(CurrentMember::class, Members::class);
        $services->alias(TrustedHiders::class, Members::class);
        $services->set(Posts::class)->args(['%kernel.cache_dir%/actions.log'])->autoconfigure();
        $services->set(PostController::class)->args([new Reference('twig')])->tag('controller.service_arguments')->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@PhilModerationBundle/config/routes/admin.php')->prefix('/admin/moderation');
        $routes->import('@PhilModerationBundle/config/routes/report.php')->prefix('/moderation');
        $routes->import('@PhilModerationBundle/config/routes/journal.php')->prefix('/moderation/journal');
        $routes->import('@PhilModerationBundle/config/routes/statement.php')->prefix('/moderation/statements');
        $routes->add('post', '/forum/1')->controller(PostController::class);
    }
}
