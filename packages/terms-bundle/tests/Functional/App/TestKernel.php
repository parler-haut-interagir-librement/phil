<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Functional\App;

use Override;
use Phil\TermsBundle\Tests\Unit\Fixture\ArrayTokenStorage;
use Phil\TermsBundle\Tests\Unit\Fixture\FixedMember;
use Phil\TermsBundle\Tests\Unit\Fixture\KitRecipes;
use Phil\TermsBundle\Tests\Unit\Fixture\ListedMembers;
use Phil\TermsBundle\Tests\Unit\Fixture\RoleChecker;
use Phil\TermsBundle\Tests\Unit\Fixture\TailwindFilters;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Twig\Extra\Html\HtmlExtension;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * An application as small as the bundle needs: FrameworkBundle, DoctrineBundle on SQLite — or on the server
 * `PHIL_TERMS_TEST_DATABASE_URL` names —, TwigBundle with the translator, TwigComponentBundle with the recipes of one kit
 * copied where `ux:install` would put them — the PHIL UX kit in a subdirectory of the components or at their root, or
 * the Shadcn kit of the Symfony UX Toolkit, the Toolkit itself stood for by its parameter —, the pages under `/terms`,
 * the administration under `/admin/terms`, a page of its own at `/forum` and an API at `/api/posts`. The member comes
 * from the `X-Member` header, the roles from `X-Roles`, and the CSRF tokens are kept in memory.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(
        string $environment,
        bool $debug,
        private readonly string $kit = KitRecipes::PHIL,
        private readonly string $subdirectory = 'ui',
    ) {
        parent::__construct($environment, $debug);
    }

    public static function databaseUrl(): string
    {
        $url = getenv('PHIL_TERMS_TEST_DATABASE_URL');

        return \is_string($url) && '' !== $url ? $url : 'sqlite:///%kernel.cache_dir%/terms.sqlite';
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

    public function forum(): Response
    {
        return new Response('forum');
    }

    public function posts(): JsonResponse
    {
        return new JsonResponse(['posts' => []]);
    }

    private function root(): string
    {
        return sys_get_temp_dir().'/phil-terms-bundle-test/'.hash('xxh128', __DIR__.self::databaseUrl().$this->kit.$this->subdirectory);
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
            'translator' => ['default_path' => __DIR__.'/translations', 'fallbacks' => ['en']],
        ]);

        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->set('security.authorization_checker', RoleChecker::class)->args([service('request_stack')])->public();
        $services->set(ArrayTokenStorage::class);
        $services->set('security.csrf.token_manager', CsrfTokenManager::class)->args([null, service(ArrayTokenStorage::class)])->public();
        $services->set('app.member', FixedMember::class)->args([null, service('request_stack')]);
        $services->set('app.members', ListedMembers::class);

        $container->extension('doctrine', [
            'dbal' => ['url' => self::databaseUrl()],
            'orm' => [],
        ]);

        $container->extension('twig', ['strict_variables' => true, 'paths' => [KitRecipes::install($this->kit, $this->subdirectory, $this->root())]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);

        if (KitRecipes::SHADCN === $this->kit) {
            $services->set(HtmlExtension::class)->autoconfigure();
            $services->set(TailwindFilters::class)->autoconfigure();
        }

        $container->extension('phil_terms', [
            'locales' => ['en', 'fr'],
            'policies' => ['terms' => [], 'privacy' => ['renew_after_days' => 365]],
            'member_resolver' => 'app.member',
            'member_source' => 'app.members',
            'gate' => ['api_paths' => ['/api/']],
            'admin' => ['enabled' => true, 'role' => 'ROLE_LEGAL'],
        ]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@PhilTermsBundle/config/routes/pages.php')->prefix('/terms');
        $routes->import('@PhilTermsBundle/config/routes/admin.php')->prefix('/admin/terms');
        $routes->add('forum', '/forum')->controller($this->forum(...));
        $routes->add('posts', '/api/posts')->controller($this->posts(...));
    }
}
