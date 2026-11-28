<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Functional\App;

use FilesystemIterator;
use LogicException;
use Override;
use Phil\EmailTemplatesBundle\Infrastructure\DependencyInjection\EmailTemplatesServices;
use Psr\Log\NullLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Twig\Extra\Html\HtmlExtension;

/**
 * An application as small as the bundle needs: FrameworkBundle with the router, the translator, the CSRF protection,
 * Mailer and Messenger in memory; DoctrineBundle on SQLite — or on the server `PHIL_EMAIL_TEMPLATES_TEST_DATABASE_URL`
 * names, to run the same store on MySQL or PostgreSQL —; PhilContentBundle serving `en`, `fr` and `fr_CA`, where the
 * templates are kept; SecurityBundle with two users in memory; TwigBundle and
 * TwigComponentBundle with the recipes of the kit copied where `ux:install` would put them; and the bundle, its
 * administration enabled for `ROLE_MAIL`, its routes under `/admin/email-templates`, the default files and the images
 * of the fixtures, an extension of the sandbox and a resolver of deliveries.
 *
 * The recipes are those of the PHIL UX kit in the subdirectory `ui`, or those of the Shadcn kit of the Symfony UX
 * Toolkit at the root of the components in the environment `shadcn`, with Twig's HTML extension for their variants,
 * the filters of Tailwind's class merging returning the classes as they come, and an icon drawn empty.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /** The recipes the pages of the administration call. */
    public const array RECIPES = ['alert', 'badge', 'button', 'field', 'input', 'native-select', 'table', 'textarea'];

    public const string SHADCN = 'shadcn';

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

    /**
     * @param string|null $databaseUrl the database of the application, a SQLite file of its own when null
     */
    public function __construct(string $environment, bool $debug, private readonly ?string $databaseUrl = null)
    {
        parent::__construct($environment, $debug);
    }

    /**
     * The database the kernel runs on: the one it was given, the server `PHIL_EMAIL_TEMPLATES_TEST_DATABASE_URL` names,
     * or a SQLite file of its own.
     */
    public function databaseUrl(): string
    {
        $url = $this->databaseUrl ?? getenv('PHIL_EMAIL_TEMPLATES_TEST_DATABASE_URL');

        return \is_string($url) && '' !== $url ? $url : 'sqlite:///%kernel.cache_dir%/templates.sqlite';
    }

    public function isShadcn(): bool
    {
        return self::SHADCN === $this->environment;
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return $this->root().'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->root().'/log';
    }

    private function root(): string
    {
        // One container per database: the URL is compiled into it.
        return sys_get_temp_dir().'/phil-email-templates-bundle-test/'.hash('xxh128', __DIR__.$this->databaseUrl());
    }

    /**
     * The templates of the recipes, where the kit's manifests copy them.
     */
    private function installRecipes(): string
    {
        $shadcn = $this->isShadcn();
        $root = $this->root().($shadcn ? '/shadcn' : '/phil');
        $target = $root.'/templates/components'.($shadcn ? '' : '/ui');
        $kit = $shadcn
            ? self::shadcnKit() ?? throw new LogicException('The Symfony UX Toolkit is not installed.') : array_find([__DIR__.'/../../../../ux-kit', __DIR__.'/../../../../../vendor/phil/ux-kit'], static fn (string $directory): bool => is_file($directory.'/manifest.json')) ?? __DIR__.'/../../../vendor/phil/ux-kit';

        if ($shadcn) {
            // The Shadcn recipes call the `Label` of their kit and the icons of Symfony UX Icons by their root names.
            if (!is_dir($target.'/ux')) {
                mkdir($target.'/ux', recursive: true);
            }

            file_put_contents($target.'/ux/icon.html.twig', '{% props name %}<svg data-icon="{{ name }}" aria-hidden="true"{{ attributes }}></svg>');
        }

        foreach ($shadcn ? [...self::RECIPES, 'label'] : self::RECIPES as $recipe) {
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

        return $root.'/templates';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $shadcn = $this->isShadcn();
        $container->parameters()->set('.ux_toolkit.components.subdirectory', $shadcn ? '' : 'ui');
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
            'mailer' => ['dsn' => 'null://null'],
            'messenger' => ['transports' => ['sync' => 'sync://']],
        ]);
        $container->extension('doctrine', ['dbal' => ['url' => $this->databaseUrl()], 'orm' => []]);
        $container->extension('phil_content', ['locales' => ['en' => [], 'fr' => [], 'fr_CA' => ['fr']]]);
        $container->extension('security', [
            'password_hashers' => [InMemoryUser::class => 'plaintext'],
            'providers' => ['users' => ['memory' => ['users' => [
                'editor-1' => ['password' => 'x', 'roles' => ['ROLE_MAIL']],
                'reader' => ['password' => 'x', 'roles' => ['ROLE_USER']],
            ]]]],
            'firewalls' => ['main' => ['lazy' => true, 'provider' => 'users']],
        ]);
        $container->extension('twig', ['strict_variables' => true, 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('phil_email_templates', [
            'site' => 'main',
            'defaults_paths' => [__DIR__.'/../../Fixtures/defaults'],
            'embeds' => ['directory' => __DIR__.'/../../Fixtures/images'],
            'brand' => ['name' => 'Test shop'],
            'from' => 'Test shop <shop@example.com>',
            'sandbox' => ['filters' => ['shout'], 'cache' => true],
            'admin' => ['enabled' => true, 'role' => 'ROLE_MAIL'],
            'messenger' => ['delivery_resolver' => Deliveries::class],
        ]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set('logger', NullLogger::class);
        $services->set('clock', MockClock::class)->args(['2026-11-18 10:00:00 UTC'])->public();
        $services->set(Deliveries::class);
        $services->set(Shout::class)->autoconfigure(false)->tag(EmailTemplatesServices::SANDBOX_EXTENSION_TAG);

        if ($shadcn) {
            $services->set(HtmlExtension::class);
            $services->set(TailwindFilters::class);
        }
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 3).'/config/routes_admin.php')->prefix('/admin/email-templates');
    }
}
