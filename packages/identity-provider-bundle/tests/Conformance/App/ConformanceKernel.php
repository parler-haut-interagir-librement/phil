<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Conformance\App;

use FilesystemIterator;
use Override;
use Phil\IdentityProviderBundle\Application\Accounts;
use Phil\IdentityProviderBundle\Application\AccountUser;
use Phil\IdentityProviderBundle\Application\Oidc\ClientRegistry;
use Phil\IdentityProviderBundle\Application\Oidc\SigningKeys;
use Phil\IdentityProviderBundle\Domain\Contract\AccountRepository;
use Phil\IdentityProviderBundle\Infrastructure\DependencyInjection\IdentityProviderServices;
use Psr\Log\NullLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * The application the OpenID conformance suite plays against: the bundles of config/bundles.php, the bundle with its
 * password and OpenID Connect modules, on SQLite, behind a sign-in form the suite's browser fills, with real sessions and the system clock. Its
 * issuer is the https address the suite reaches it at, from the environment.
 */
final class ConformanceKernel extends Kernel
{
    use MicroKernelTrait;

    /** The recipes the templates of the bundle call. */
    public const array RECIPES = ['alert', 'button', 'card', 'field', 'input'];

    public const string LOGIN_CSRF_ID = 'authenticate';

    public const string ACCOUNTS = 'conformance.accounts';

    public const string ACCOUNT_STORE = 'conformance.account_store';

    public const string CLIENTS = 'conformance.clients';

    public const string KEYS = 'conformance.keys';

    public const string ENTITY_MANAGER = 'conformance.entity_manager';

    public function __construct(
        private readonly string $issuer,
        private readonly string $root,
    ) {
        parent::__construct('conformance', false);
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return $this->root.'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->root.'/log';
    }

    /**
     * The sign-in form of the firewall, as plain as the suite's browser needs: fields named `_username` and
     * `_password`, a CSRF token, one button.
     */
    public function login(AuthenticationUtils $utils, CsrfTokenManagerInterface $csrf): Response
    {
        $error = $utils->getLastAuthenticationError();

        return new Response(\sprintf(
            '<!doctype html><title>Sign in</title><form method="post" action="/login"><p class="error">%s</p><input name="_username" value="%s"><input name="_password" type="password"><input type="hidden" name="_csrf_token" value="%s"><button type="submit" id="sign-in">Sign in</button></form>',
            $error instanceof AuthenticationException ? htmlspecialchars($error->getMessageKey(), \ENT_QUOTES) : '',
            htmlspecialchars($utils->getLastUsername(), \ENT_QUOTES),
            htmlspecialchars($csrf->getToken(self::LOGIN_CSRF_ID)->getValue(), \ENT_QUOTES),
        ));
    }

    public function home(): Response
    {
        return new Response('<!doctype html><title>Signed in</title><p>Signed in.</p>');
    }

    private function installRecipes(): string
    {
        $target = $this->root.'/templates/components';
        $kit = array_find(
            [__DIR__.'/../../../../ux-kit', __DIR__.'/../../../../../vendor/phil/ux-kit', __DIR__.'/../../../../../../phil/packages/ux-kit'],
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

        return $this->root.'/templates';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => hash('sha256', $this->root.$this->issuer),
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => ['utf8' => true],
            'default_locale' => 'en',
            'translator' => ['fallbacks' => ['en']],
            'csrf_protection' => true,
            'session' => ['handler_id' => null, 'save_path' => $this->root.'/sessions', 'cookie_secure' => true, 'cookie_samesite' => 'lax'],
            'mailer' => ['dsn' => 'null://null'],
        ]);
        $container->services()->set('logger', NullLogger::class);
        $container->services()->set('clock', NativeClock::class)->public();
        // What bin/setup.php reaches to create the tables, the account, the clients and the first key.
        $container->services()->alias(self::ACCOUNTS, Accounts::class)->public();
        $container->services()->alias(self::ACCOUNT_STORE, AccountRepository::class)->public();
        $container->services()->alias(self::CLIENTS, ClientRegistry::class)->public();
        $container->services()->alias(self::KEYS, SigningKeys::class)->public();
        $container->services()->alias(self::ENTITY_MANAGER, 'doctrine.orm.entity_manager')->public();

        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///'.$this->root.'/identity.sqlite'],
            'orm' => [],
        ]);
        $container->extension('twig', ['strict_variables' => true, 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);

        $container->extension('security', [
            'password_hashers' => [AccountUser::class => 'auto'],
            'providers' => ['accounts' => ['id' => IdentityProviderServices::USER_PROVIDER]],
            'firewalls' => ['main' => [
                'lazy' => true,
                'provider' => 'accounts',
                'user_checker' => IdentityProviderServices::USER_CHECKER,
                'form_login' => ['login_path' => 'app_login', 'check_path' => 'app_login', 'enable_csrf' => true, 'csrf_token_id' => self::LOGIN_CSRF_ID, 'default_target_path' => '/home'],
                'entry_point' => 'form_login',
                're_authentication_entry_point' => IdentityProviderServices::RE_AUTHENTICATION_ENTRY_POINT,
                'logout' => ['path' => 'app_logout'],
            ]],
        ]);

        $container->extension('phil_identity_provider', [
            'site_name' => 'Conformance',
            'modules' => ['oidc' => true],
            'login_route' => 'app_login',
            'default_target' => '/home',
            'open_routes' => ['app_logout'],
            'oidc' => ['issuer' => $this->issuer],
            'audit' => 'none',
        ]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('app_login', '/login')->controller('kernel::login');
        $routes->add('app_logout', '/logout');
        $routes->add('app_home', '/home')->controller('kernel::home');
        $routes->import('.', 'phil_identity_provider');
    }
}
