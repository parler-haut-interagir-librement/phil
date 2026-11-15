<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle;

use Override;
use Phil\IdentityProviderBundle\Application\AccountUser;
use Phil\IdentityProviderBundle\Application\Modules;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use Phil\IdentityProviderBundle\Infrastructure\DependencyInjection\IdentityProviderServices;
use Phil\IdentityProviderBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * An open identity brick, one module per method, each with its routes, tables and pages:
 *
 * ```yaml
 * phil_identity_provider:
 *     site_name: Example
 *     modules: { passkeys: true, totp: true, login_link: false, sms: false, oidc: true }
 *     passkeys: { rp_id: example.com, origins: ['https://example.com'] }
 *     oidc: { issuer: 'https://example.com' }
 * ```
 *
 * The firewall names the bundle's user provider, user checker, authenticators and re-authentication entry point;
 * the routes are imported with the type `phil_identity_provider`. The models of the modules turned on are mapped for
 * Doctrine, and the accounts' passwords are hashed with Argon2id.
 */
final class PhilIdentityProviderBundle extends AbstractBundle
{
    public const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    #[Override]
    protected string $extensionAlias = 'phil_identity_provider';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()->children()
            ->booleanNode('enabled')->info("Registers the bundle's services.")->defaultTrue()->end()
            ->scalarNode('secret')->info('The secret the links sent by email are signed with and the recovery codes are keyed with, at least 32 characters, from the secrets vault.')->defaultValue('%kernel.secret%')->cannotBeEmpty()->end()
            ->scalarNode('site_name')->info('The name of the site, in emails, in the authenticator application and on the passkeys.')->defaultValue('PHIL')->cannotBeEmpty()->end()
            ->arrayNode('modules')
                ->info('The modules turned on; one off has no route, no table and no page.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->booleanNode(Modules::ACCOUNTS)->info('Accounts, registration, verified addresses, the account page; always on.')->defaultTrue()->end()
                    ->booleanNode(Modules::PASSWORD)->info('Argon2id passwords checked by phil/passwords, their reset, and the progressive lock.')->defaultTrue()->end()
                    ->booleanNode(Modules::PASSKEYS)->info('Passkeys (WebAuthn): registration, sign-in, list and removal.')->defaultFalse()->end()
                    ->booleanNode(Modules::TOTP)->info('The authenticator application (TOTP) as a second factor.')->defaultFalse()->end()
                    ->booleanNode(Modules::RECOVERY_CODES)->info('Recovery codes standing in for the authenticator application.')->defaultFalse()->end()
                    ->booleanNode(Modules::LOGIN_LINK)->info('Sign-in links by email, through the login_link authenticator of Symfony Security.')->defaultFalse()->end()
                    ->booleanNode(Modules::SMS)->info('The verification of a phone number by a code sent through the Notifier component.')->defaultFalse()->end()
                    ->booleanNode(Modules::SESSIONS)->info('Devices and sessions: the list, a session closed from another device, all closed at once.')->defaultTrue()->end()
                    ->booleanNode(Modules::STEP_UP)->info('Step-up: PHIL_STEP_UP and the re-authentication page.')->defaultTrue()->end()
                    ->booleanNode(Modules::EXTERNAL)->info('Federated providers, whose packages plug into the port ExternalProvider.')->defaultFalse()->end()
                    ->booleanNode(Modules::OIDC)->info('The OpenID Connect provider.')->defaultFalse()->end()
                ->end()
            ->end()
            ->enumNode('store')->info('Where the modules keep their rows: "doctrine", or "memory" for tests.')->values([IdentityProviderServices::STORE_DOCTRINE, IdentityProviderServices::STORE_MEMORY])->defaultValue(IdentityProviderServices::STORE_DOCTRINE)->end()
            ->scalarNode('entity_manager')->info('The Doctrine entity manager of the tables.')->defaultValue('default')->cannotBeEmpty()->end()
            ->scalarNode('clock')->info('Service id of the PSR-20 clock.')->defaultValue('clock')->cannotBeEmpty()->end()
            ->scalarNode('layout')->info('The template the pages extend.')->defaultValue('@PhilIdentityProvider/layout.html.twig')->cannotBeEmpty()->end()
            ->scalarNode('kit_subdirectory')->info('Where the recipes of the kit are, under the components directory; the subdirectory of ux_toolkit when null.')->defaultNull()->end()
            ->booleanNode('registration')->info('Whether anyone may register.')->defaultTrue()->end()
            ->booleanNode('require_verified_email')->info('Whether an account signs in only once its address is verified.')->defaultFalse()->end()
            ->scalarNode('login_route')->info('The route of the sign-in form of the firewall, offered for a re-authentication by password and after a failed external sign-in.')->defaultNull()->end()
            ->scalarNode('default_target')->info('Where a sign-in by the bundle goes when nothing asked for a page.')->defaultValue('/')->cannotBeEmpty()->end()
            ->arrayNode('open_routes')->info('The routes reachable before the second step of a sign-in, such as the logout route.')->scalarPrototype()->end()->end()
            ->arrayNode('mailer')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('from')->info('The sender of the emails; null sends none.')->defaultNull()->end()
                ->end()
            ->end()
            ->arrayNode('password')
                ->addDefaultsIfNotSet()
                ->children()
                    ->integerNode('min_score')->info('The lowest score of the strength estimator of phil/passwords, 0 to 4.')->defaultValue(2)->min(0)->max(4)->end()
                    ->booleanNode('breach_check')->info('Whether a new password is looked up in Pwned Passwords, by k-anonymity.')->defaultFalse()->end()
                    ->scalarNode('http_client')->info('Service id of the HTTP client of the breach check.')->defaultValue('http_client')->cannotBeEmpty()->end()
                ->end()
            ->end()
            ->arrayNode('lockout')
                ->addDefaultsIfNotSet()
                ->children()
                    ->integerNode('threshold')->info('The failed sign-ins in a row before the first lock.')->defaultValue(5)->min(1)->end()
                    ->integerNode('base')->info('The seconds of the first lock, doubled at each further failure.')->defaultValue(60)->min(1)->end()
                    ->integerNode('max')->info('The longest lock, in seconds.')->defaultValue(3600)->min(1)->end()
                ->end()
            ->end()
            ->arrayNode('links')
                ->addDefaultsIfNotSet()
                ->children()
                    ->integerNode('verify_lifetime')->info('The seconds a link that verifies an address lasts.')->defaultValue(86_400)->min(60)->end()
                    ->integerNode('reset_lifetime')->info('The seconds a link that resets a password lasts.')->defaultValue(3_600)->min(60)->end()
                ->end()
            ->end()
            ->arrayNode('passkeys')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('rp_id')->info('The relying party identifier: the domain of the site, such as example.com.')->defaultNull()->end()
                    ->scalarNode('rp_name')->info('The name the authenticator shows; the site name when null.')->defaultNull()->end()
                    ->arrayNode('origins')->info('The origins the pages are served from, such as https://example.com.')->scalarPrototype()->cannotBeEmpty()->end()->end()
                    ->booleanNode('require_user_verification')->info('Whether a passkey must verify the person by a PIN or a biometric.')->defaultFalse()->end()
                ->end()
            ->end()
            ->arrayNode('totp')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('issuer')->info('The issuer the authenticator application shows; the site name when null.')->defaultNull()->end()
                    ->integerNode('window')->info('The periods of 30 seconds accepted before and after the current one.')->defaultValue(1)->min(0)->max(10)->end()
                ->end()
            ->end()
            ->arrayNode('sms')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('sender')->info('Service id of an SmsSender; the texter of the Notifier component when null.')->defaultNull()->end()
                    ->scalarNode('from')->info('The sender of the text messages, when the transport allows one.')->defaultValue('')->end()
                    ->scalarNode('message')->info('The text of the message, {code} replaced by the code.')->defaultValue('Your code: {code}')->cannotBeEmpty()->end()
                    ->integerNode('lifetime')->info('The seconds a code lasts.')->defaultValue(600)->min(60)->end()
                    ->integerNode('attempts')->info('The tries a code allows.')->defaultValue(5)->min(1)->end()
                ->end()
            ->end()
            ->arrayNode('step_up')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('attribute')->info('The attribute the sensitive actions of the bundle need: PHIL_STEP_UP, or IS_AUTHENTICATED_RECENTLY of Symfony Security.')->defaultValue('PHIL_STEP_UP')->cannotBeEmpty()->end()
                    ->integerNode('lifetime')->info('The seconds a proof grants PHIL_STEP_UP.')->defaultValue(300)->min(30)->end()
                ->end()
            ->end()
            ->arrayNode('sessions')
                ->addDefaultsIfNotSet()
                ->children()
                    ->integerNode('touch_interval')->info('The fewest seconds between two writes of when a session was last seen.')->defaultValue(300)->min(0)->end()
                ->end()
            ->end()
            ->arrayNode('external')
                ->addDefaultsIfNotSet()
                ->children()
                    ->booleanNode('link_verified_email')->info('Whether an identity whose provider verified an address links to the account that verified the same address, instead of asking the person to sign in first.')->defaultFalse()->end()
                    ->booleanNode('register')->info('Whether an unknown external identity creates an account.')->defaultTrue()->end()
                ->end()
            ->end()
            ->arrayNode('oidc')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('issuer')->info('The issuer: the https address clients know the provider by, without a trailing slash.')->defaultNull()->end()
                    ->enumNode('algorithm')->info('The algorithm of new signing keys.')->values(array_map(static fn (SigningAlgorithm $algorithm): string => $algorithm->value, SigningAlgorithm::cases()))->defaultValue(SigningAlgorithm::RS256->value)->end()
                    ->integerNode('code_lifetime')->info('The seconds an authorization code lasts, at most 600.')->defaultValue(60)->min(1)->max(600)->end()
                    ->integerNode('access_token_lifetime')->info('The seconds an access token lasts.')->defaultValue(600)->min(1)->end()
                    ->integerNode('id_token_lifetime')->info('The seconds an ID token lasts.')->defaultValue(600)->min(1)->end()
                    ->integerNode('refresh_token_lifetime')->info('The seconds a refresh token lasts.')->defaultValue(2_592_000)->min(1)->end()
                    ->integerNode('key_activation_delay')->info('The seconds a new key is published before it signs.')->defaultValue(86_400)->min(0)->end()
                    ->integerNode('retired_key_retention')->info('The seconds a retired key stays published.')->defaultValue(604_800)->min(0)->end()
                    ->booleanNode('allow_localhost')->info('Whether redirect addresses on localhost over http are accepted, for development.')->defaultFalse()->end()
                ->end()
            ->end()
            ->arrayNode('secrets')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('kms_key')->info('The key of the KeyManagement component that encrypts TOTP secrets and signing keys; null keeps them in clear.')->defaultNull()->end()
                    ->scalarNode('encrypter')->info('Service id of the encrypter of the KeyManagement component.')->defaultValue('key_management.encrypter')->cannotBeEmpty()->end()
                    ->scalarNode('decrypter')->info('Service id of the decrypter of the KeyManagement component.')->defaultValue('key_management.decrypter')->cannotBeEmpty()->end()
                ->end()
            ->end()
            ->scalarNode('audit')->info('"auto" writes to phil/audit-bundle when it is installed, "none" nowhere, or the id of an AuditRecorder.')->defaultValue('auto')->cannotBeEmpty()->end()
        ->end();
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new KitSubdirectoryPass());
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $enabled = $this->enabledModules($builder->getExtensionConfig($this->extensionAlias));

        if ($builder->hasExtension('doctrine')) {
            $mappings = [];

            foreach (IdentityProviderServices::MODEL_DIRECTORIES as $module => $directory) {
                if (\in_array($module, $enabled, true)) {
                    $mappings['PhilIdentityProvider'.$directory] = [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => __DIR__.'/Domain/Model/'.$directory,
                        'prefix' => 'Phil\IdentityProviderBundle\Domain\Model\\'.$directory,
                    ];
                }
            }

            $container->extension('doctrine', ['orm' => ['mappings' => $mappings]]);
        }

        if ($builder->hasExtension('security')) {
            $container->extension('security', ['password_hashers' => [AccountUser::class => ['algorithm' => 'sodium']]]);
        }

        // AssetMapperBundle has its own extension since Symfony 8.2; `framework.asset_mapper` is only an alias of it.
        if ($builder->hasExtension('asset_mapper')) {
            $container->extension('asset_mapper', ['paths' => [self::CONTROLLERS_DIR => '@phil/identity-provider-bundle']]);
        }
    }

    /**
     * @param array<array-key, mixed> $config the configuration, processed by the tree above
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('phil_identity_provider.enabled', true === $config['enabled']);

        if (true !== $config['enabled']) {
            return;
        }

        unset($config['enabled']);
        /** @phpstan-var array{secret: string, site_name: string, modules: array<string, bool>, store: string, entity_manager: string, clock: string, layout: string, kit_subdirectory: ?string, registration: bool, require_verified_email: bool, login_route: ?string, default_target: string, open_routes: list<string>, mailer: array{from: ?string}, password: array{min_score: int<0, 4>, breach_check: bool, http_client: string}, lockout: array{threshold: positive-int, base: positive-int, max: positive-int}, links: array{verify_lifetime: positive-int, reset_lifetime: positive-int}, passkeys: array{rp_id: ?string, rp_name: ?string, origins: list<string>, require_user_verification: bool}, totp: array{issuer: ?string, window: int<0, 10>}, sms: array{sender: ?string, from: string, message: string, lifetime: positive-int, attempts: positive-int}, step_up: array{attribute: string, lifetime: positive-int}, sessions: array{touch_interval: non-negative-int}, external: array{link_verified_email: bool, register: bool}, oidc: array{issuer: ?string, algorithm: string, code_lifetime: positive-int, access_token_lifetime: positive-int, id_token_lifetime: positive-int, refresh_token_lifetime: positive-int, key_activation_delay: non-negative-int, retired_key_retention: non-negative-int, allow_localhost: bool}, secrets: array{kms_key: ?string, encrypter: string, decrypter: string}, audit: string} $config */
        IdentityProviderServices::register($builder, $config);
    }

    /**
     * The modules the raw configurations turn on, read before they are processed, for the Doctrine mappings: the
     * defaults that own a model, then each configuration in order.
     *
     * @param list<mixed> $configs
     *
     * @return list<string>
     */
    private function enabledModules(array $configs): array
    {
        $modules = [Modules::ACCOUNTS => true, Modules::SESSIONS => true];

        foreach ($configs as $config) {
            if (\is_array($config) && \is_array($config['modules'] ?? null)) {
                foreach ($config['modules'] as $module => $on) {
                    $modules[(string) $module] = true === $on;
                }
            }
        }

        return array_keys(array_filter($modules));
    }
}
