<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Tests\Functional\App;

use FilesystemIterator;
use LogicException;
use Override;
use Psr\Log\NullLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Twig\Extra\Html\HtmlExtension;

/**
 * An application as small as the bundle needs: FrameworkBundle, DoctrineBundle on SQLite, TwigBundle with the
 * translator, TwigComponentBundle with the recipes of one kit copied where `ux:install` would put them — in the
 * subdirectory of the application's Toolkit configuration, the Toolkit itself stood for by its parameter. The kit is
 * the PHIL UX kit, or with `SHADCN` the Shadcn kit of the Symfony UX Toolkit, at the root of the components as its
 * recipes call one another by their root names, with Twig's HTML extension for their variants and the filters of
 * Tailwind's class merging giving the classes back as they come —, Symfony
 * Security with an administrator and a reader signing in by HTTP Basic, the statement kept in a table with its first
 * version in the configuration, the audit read from a directory of the cache, a contact page, the page of a form, and a
 * page whose footer links to the statement.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /** The recipes the templates of the bundle call. */
    public const array RECIPES = ['alert', 'badge', 'button', 'card', 'field', 'table', 'textarea'];

    /** The kit of the Symfony UX Toolkit drawn on Tailwind. */
    public const string SHADCN = 'shadcn';

    /** The recipes the Shadcn recipes call in turn. */
    private const array SHADCN_RECIPES = ['label', 'separator'];

    /**
     * The directory of the Shadcn kit, `null` when the Symfony UX Toolkit is not installed.
     */
    public static function shadcnKit(): ?string
    {
        return array_find(
            [__DIR__.'/../../../../../vendor/symfony/ux-toolkit/kits/shadcn', __DIR__.'/../../../vendor/symfony/ux-toolkit/kits/shadcn'],
            static fn (string $directory): bool => is_file($directory.'/manifest.json'),
        );
    }

    public function __construct(
        string $environment,
        bool $debug,
        private readonly string $kit = 'ux-kit',
        private readonly string $subdirectory = 'ui',
    ) {
        parent::__construct($environment, $debug);
    }

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

    public function auditDirectory(): string
    {
        return $this->getCacheDir().'/audit';
    }

    public function contact(): Response
    {
        return new Response('contact');
    }

    private function root(): string
    {
        return sys_get_temp_dir().'/phil-accessibility-statement-bundle-test/'.hash('xxh128', __DIR__.$this->kit.$this->subdirectory);
    }

    /**
     * The templates of the recipes, where the kit's manifests copy them.
     */
    private function installRecipes(): string
    {
        $target = rtrim($this->root().'/templates/components/'.$this->subdirectory, '/');
        $recipes = self::RECIPES;

        if (self::SHADCN === $this->kit) {
            $kit = self::shadcnKit() ?? throw new LogicException('The Symfony UX Toolkit is not installed.');
            $recipes = [...$recipes, ...self::SHADCN_RECIPES];
        } else {
            // The kit beside this package in the monorepo, else among the development requirements of the monorepo's
            // root, else among those of the package alone: a kit is a directory with its manifest, not a directory
            // left behind by a kit that moved away, with only its caches.
            $kit = array_find(
                [__DIR__.'/../../../../'.$this->kit, __DIR__.'/../../../../../vendor/phil/'.$this->kit],
                static fn (string $directory): bool => is_file($directory.'/manifest.json'),
            ) ?? __DIR__.'/../../../vendor/phil/'.$this->kit;
        }

        foreach ($recipes as $recipe) {
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
        $container->parameters()->set('.ux_toolkit.components.subdirectory', '' === $this->subdirectory ? null : $this->subdirectory);
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
        ]);

        $services = $container->services();
        $services->set('logger', NullLogger::class);

        if (self::SHADCN === $this->kit) {
            $services->set(HtmlExtension::class)->autoconfigure();
            $services->set(TailwindFilters::class)->autoconfigure();
        }

        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///%kernel.cache_dir%/statement.sqlite'],
            'orm' => [],
        ]);

        $container->extension('twig', ['strict_variables' => true, 'default_path' => __DIR__.'/templates', 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);

        $container->extension('security', [
            'password_hashers' => [InMemoryUser::class => 'plaintext'],
            'providers' => ['people' => ['memory' => ['users' => [
                'admin' => ['password' => 'admin', 'roles' => ['ROLE_ADMIN']],
                'reader' => ['password' => 'reader', 'roles' => ['ROLE_USER']],
            ]]]],
            'firewalls' => ['main' => ['http_basic' => null, 'provider' => 'people']],
        ]);

        $container->extension('phil_accessibility_statement', [
            'source' => 'doctrine',
            'feedback_route' => 'contact',
            'statement' => [
                'version' => 1,
                'published_at' => '2026-11-01',
                'entity' => 'Example Org',
                'feedback' => ['email' => 'accessibility@example.com'],
            ],
            'audit' => ['enabled' => true, 'directory' => '%kernel.cache_dir%/audit'],
            'admin' => ['enabled' => true],
        ]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@PhilAccessibilityStatementBundle/config/routes.php');
        $routes->import('@PhilAccessibilityStatementBundle/config/admin_routes.php')->prefix('/admin');
        $routes->add('contact', '/contact')->controller($this->contact(...));
        $routes->add('phil_forms_form', '/forms/{key}')->controller($this->contact(...));
    }
}
