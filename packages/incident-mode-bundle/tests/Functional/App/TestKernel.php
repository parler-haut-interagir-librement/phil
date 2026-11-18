<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Tests\Functional\App;

use Doctrine\DBAL\Connection;
use FilesystemIterator;
use LogicException;
use Override;
use Phil\IncidentMode\Dbal\IncidentTables;
use Phil\IncidentMode\Messenger\LiftIncident;
use Psr\Log\NullLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Extra\Html\HtmlExtension;

/**
 * An application as small as the bundle needs: FrameworkBundle with its CSRF protection, the forms, the translator in
 * English and Messenger on an in-memory transport, DoctrineBundle on a SQLite file of its own — or the server of the
 * environment's URL —, TwigBundle with the components and the recipes of the kit copied where `ux:install` would put
 * them, the member named by the `X-Member` header and their roles by `X-Roles`, the established members those whose
 * identifier starts with `trusted-`, the administration under `/admin/incidents`, the status under `/status` and the
 * pages of the application.
 *
 * The recipes are those of the PHIL UX kit, or of the Shadcn kit of the Symfony UX Toolkit in the environments ending
 * in `shadcn`, with Twig's HTML extension for their variants, the filters of Tailwind's class merging returning the
 * classes as they come, and an icon drawn empty.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /** The recipes the templates of the bundle call. */
    public const array RECIPES = ['alert', 'badge', 'button', 'field', 'input', 'native-select', 'table', 'textarea'];

    public const string SHADCN = 'shadcn';

    public function __construct(
        string $environment,
        bool $debug,
        private readonly ?string $databaseUrl = null,
    ) {
        parent::__construct($environment, $debug);
    }

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

    public function isShadcn(): bool
    {
        return str_ends_with($this->environment, self::SHADCN);
    }

    /**
     * Creates the table of the incidents again, empty.
     */
    public function resetDatabase(): void
    {
        $connection = $this->getContainer()->get('doctrine.dbal.default_connection');
        \assert($connection instanceof Connection);
        $schema = $connection->createSchemaManager();

        if ($schema->tablesExist([IncidentTables::ACTIVATIONS])) {
            $schema->dropTable(IncidentTables::ACTIVATIONS);
        }

        $schema->createTable(IncidentTables::table());
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return $this->base().'/cache/'.$this->environment;
    }

    #[Override]
    public function getShareDir(): string
    {
        return $this->base().'/share/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->base().'/log';
    }

    private function base(): string
    {
        return sys_get_temp_dir().'/phil-incident-mode-bundle-test/'.hash('xxh128', __DIR__.$this->databaseUrl);
    }

    /**
     * The templates of the recipes, where the kit's manifests copy them.
     */
    private function installRecipes(): string
    {
        $shadcn = $this->isShadcn();
        $root = $this->base().($shadcn ? '/shadcn' : '/phil');
        $target = $root.'/templates/components/ui';
        $kit = $shadcn
            ? self::shadcnKit() ?? throw new LogicException('The Symfony UX Toolkit is not installed.') : array_find([__DIR__.'/../../../../../../phil/packages/ux-kit', __DIR__.'/../../../../../vendor/phil/ux-kit'], static fn (string $directory): bool => is_file($directory.'/manifest.json')) ?? __DIR__.'/../../../vendor/phil/ux-kit';

        if ($shadcn) {
            // The Shadcn recipes call the `Label` of their kit and the icons of Symfony UX Icons by their root names.
            $target = $root.'/templates/components';

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
            'secret' => 'a test secret of sixteen bytes',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'csrf_protection' => true,
            'form' => true,
            'translator' => ['fallbacks' => ['en']],
            'default_locale' => 'en',
            'messenger' => [
                'transports' => ['async' => 'in-memory://'],
                'routing' => [LiftIncident::class => 'async'],
            ],
        ]);

        if (!is_dir($this->base())) {
            mkdir($this->base(), recursive: true);
        }

        $container->extension('doctrine', ['dbal' => ['url' => $this->databaseUrl ?? 'sqlite:///'.$this->base().'/incidents-'.$this->environment.'.sqlite']]);
        $container->extension('twig', ['strict_variables' => true, 'default_path' => __DIR__.'/templates', 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('phil_incident_mode', [
            'current_member' => HeaderMember::class,
            'established_members' => TrustedMembers::class,
            'spare_established' => ['new-posts'],
            'timezone' => 'America/Toronto',
            'without_presets' => ['suspend-new-messages'],
            'presets' => [
                'suspend-listings' => ['label' => 'Suspend new listings', 'restrictions' => ['new-listings'], 'proposed' => '4h', 'longest' => '2d'],
                'suspend-messages' => ['label' => 'Pause the messages', 'restrictions' => ['new-messages'], 'proposed' => '30m', 'longest' => '3h'],
            ],
            'admin' => ['role' => 'ROLE_INCIDENTS', 'page_size' => 2],
        ]);

        $services = $container->services()->defaults()->autowire();
        $services->set('logger', NullLogger::class);
        $services->set('clock', MockClock::class)->args(['2026-11-17 15:00:00 UTC'])->public();
        $services->set(HeaderMember::class)->args([new Reference('request_stack')]);
        $services->set(TrustedMembers::class);
        $services->set('security.authorization_checker', HeaderAuthorization::class)->args([new Reference('request_stack')]);
        $services->set(PageController::class)->public()->tag('controller.service_arguments');

        if ($shadcn) {
            $services->set(HtmlExtension::class)->tag('twig.extension');
            $services->set(TailwindFilters::class)->tag('twig.attribute_extension')->tag('twig.runtime');
        }
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 3).'/config/routes/admin.php')->prefix('/admin/incidents');
        $routes->import(\dirname(__DIR__, 3).'/config/routes/status.php')->prefix('/status');
        $routes->add('sign_up', '/sign-up')->controller([PageController::class, 'signUp'])->methods(['GET', 'POST', 'DELETE']);
        $routes->add('invite', '/invite')->controller([PageController::class, 'invite'])->defaults(['_suspended_during' => 'invitations']);
        $routes->add('post', '/post')->controller([PageController::class, 'post'])->methods(['POST']);
        $routes->add('contact', '/contact')->controller([PageController::class, 'contact'])->methods(['GET', 'POST']);
        $routes->add('home', '/')->controller([PageController::class, 'banner'])->methods(['GET']);
    }
}
