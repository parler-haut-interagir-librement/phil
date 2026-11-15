<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Functional\App;

use FilesystemIterator;
use Override;
use Phil\IdentityProviderBundle\Application\AccountUser;
use Phil\IdentityProviderBundle\Infrastructure\DependencyInjection\IdentityProviderServices;
use Phil\IdentityProviderBundle\Infrastructure\Security\ExternalAuthenticator;
use Phil\IdentityProviderBundle\Infrastructure\Security\OneTimeCodeAuthenticator;
use Phil\IdentityProviderBundle\Infrastructure\Security\PasskeyAuthenticator;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\RecordingSms;
use Psr\Log\NullLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * An application as small as the bundle needs, with every module on: FrameworkBundle with sessions, CSRF, the mailer
 * and the translator; DoctrineBundle on SQLite; TwigBundle and TwigComponentBundle with the recipes of the kit copied
 * where `ux:install` puts them; Symfony Security with the bundle's provider, checker, authenticators and
 * re-authentication entry point beside a sign-in form and sign-in links; a gateway that keeps text messages; a
 * federated provider that answers at once.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /** The recipes the templates of the bundle call. */
    public const array RECIPES = ['alert', 'button', 'card', 'field', 'input'];

    /**
     * @param array<string, mixed> $identity more of the bundle's configuration
     */
    public function __construct(
        string $environment = 'test',
        bool $debug = true,
        private readonly array $identity = [],
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

    public function login(Request $request, AuthenticationUtils $utils): Response
    {
        $error = $utils->getLastAuthenticationError();

        return new Response(\sprintf(
            '<form method="post" action="/login"><p class="error">%s</p><input name="_username" value="%s"><input name="_password" type="password"><input type="hidden" name="_csrf_token" value="%s"><button>Sign in</button></form>',
            $error instanceof AuthenticationException ? htmlspecialchars($error->getMessageKey(), \ENT_QUOTES) : '',
            htmlspecialchars($utils->getLastUsername(), \ENT_QUOTES),
            '',
        ));
    }

    public function home(): Response
    {
        return new Response('home');
    }

    private function root(): string
    {
        return sys_get_temp_dir().'/phil-identity-provider-bundle-test/'.hash('xxh128', __DIR__.serialize($this->identity));
    }

    private function installRecipes(): string
    {
        $target = $this->root().'/templates/components';
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

        return $this->root().'/templates';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'a secret of the test application, long enough',
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
        ]);
        $container->services()->set('logger', NullLogger::class);
        $container->services()->set('clock', MockClock::class)->public();
        $container->services()->set('test.sms', RecordingSms::class)->public();
        $container->services()->set(FakeProvider::class)->tag('phil_identity_provider.external_provider');

        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///%kernel.cache_dir%/identity.sqlite'],
            'orm' => [],
        ]);
        $container->extension('twig', ['strict_variables' => true, 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);

        $container->extension('security', [
            'password_hashers' => [AccountUser::class => 'plaintext'],
            'providers' => ['accounts' => ['id' => IdentityProviderServices::USER_PROVIDER]],
            'firewalls' => ['main' => [
                'lazy' => true,
                'provider' => 'accounts',
                'user_checker' => IdentityProviderServices::USER_CHECKER,
                'form_login' => ['login_path' => 'app_login', 'check_path' => 'app_login', 'enable_csrf' => false, 'default_target_path' => '/home'],
                'login_link' => ['check_route' => 'phil_identity_provider_login_link_check', 'signature_properties' => ['userIdentifier', 'passwordChangedAt'], 'lifetime' => 600],
                'custom_authenticators' => [PasskeyAuthenticator::class, OneTimeCodeAuthenticator::class, ExternalAuthenticator::class],
                'entry_point' => 'form_login',
                're_authentication_entry_point' => IdentityProviderServices::RE_AUTHENTICATION_ENTRY_POINT,
                'logout' => ['path' => 'app_logout'],
            ]],
        ]);

        $container->extension('phil_identity_provider', array_replace_recursive([
            'site_name' => 'Example',
            'modules' => ['passkeys' => true, 'totp' => true, 'recovery_codes' => true, 'login_link' => true, 'sms' => true, 'external' => true, 'oidc' => true],
            'login_route' => 'app_login',
            'default_target' => '/home',
            'open_routes' => ['app_logout'],
            'mailer' => ['from' => 'accounts@example.com'],
            'passkeys' => ['rp_id' => 'localhost', 'origins' => ['http://localhost']],
            'sms' => ['sender' => 'test.sms'],
            'oidc' => ['issuer' => 'http://localhost', 'allow_localhost' => true],
            'audit' => 'none',
        ], $this->identity));
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('app_login', '/login')->controller('kernel::login');
        $routes->add('app_logout', '/logout');
        $routes->add('app_home', '/home')->controller('kernel::home');
        $routes->import('.', 'phil_identity_provider');
    }
}
