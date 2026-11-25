<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle;

use Override;
use Phil\IdentityProviderBundle\Application\AccountUser;
use Phil\IdentityProviderBundle\Application\Modules;
use Phil\IdentityProviderBundle\Application\Oidc\ProviderSettings;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use Phil\IdentityProviderBundle\Domain\Oidc\ClientAuthMethod;
use Phil\IdentityProviderBundle\Domain\Oidc\SubjectType;
use Phil\IdentityProviderBundle\Domain\WebAuthn\AttestationPolicy;
use Phil\IdentityProviderBundle\Infrastructure\DependencyInjection\IdentityProviderServices;
use Phil\IdentityProviderBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\IdentityProviderBundle\Infrastructure\DependencyInjection\LoginLinkPostOnlyPass;
use Phil\IdentityProviderBundle\Infrastructure\Routing\RouterLinkOrigin;
use Phil\RateLimit\RateLimitGuard;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Request;

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
            ->scalarNode('public_origin')->info('The canonical address of the site, such as https://example.com, that the links sent by email point to; null takes the host of the request, which framework.trusted_hosts must then bound. In the prod environment, the bundle refuses to boot when neither is set.')->defaultNull()->end()
            ->arrayNode('accounts')
                ->info('The identifiers and the addresses of the accounts.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('id_generator')->info('What gives a new account its internal identifier: "ulid", "uuid_v7", or the id of a service implementing AccountIdGenerator. The identifier stays inside the provider; clients see the sub of oidc.subject.')->defaultValue(IdentityProviderServices::ID_ULID)->cannotBeEmpty()->end()
                    ->booleanNode('strip_subaddress')->info('Whether the canonical form of an address leaves out its subaddress, "+news" in ana+news@example.com, so that an account holds the mailbox once.')->defaultFalse()->end()
                    ->scalarNode('canonicalizer')->info("The id of a service implementing AddressCanonicalizer in place of the bundle's, for the rules of a mail provider; strip_subaddress is then its own.")->defaultNull()->end()
                ->end()
            ->end()
            ->arrayNode('registration')
                ->info('Whether anyone may register, and how often; "registration: false" closes it.')
                ->canBeDisabled()
                ->children()
                    ->arrayNode('rate_limit')
                        ->info('The limits of registration, per client and per address typed, and the delay before another verification email goes to one address; "rate_limit: false" turns them off.')
                        ->canBeDisabled()
                        ->children()
                            ->append($this->limit('per_ip', 'The registrations per client address, or IPv6 /64 network, in each interval.', 10, '1 hour'))
                            ->append($this->limit('per_address', 'The registrations per address typed, known or not, in each interval.', 5, '1 hour'))
                            ->scalarNode('resend_delay')->info('The delay before another verification email goes to the same address, in the format of the Rate Limiter component.')->defaultValue('5 minutes')->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
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
                ->info('The progressive lock of an account after failed sign-ins with its password; "lockout: false" turns it off and leaves guessing to login_limiter, which nobody can use to lock the owner out.')
                ->canBeDisabled()
                ->children()
                    ->integerNode('threshold')->info('The failed sign-ins in a row before the first lock.')->defaultValue(5)->min(1)->end()
                    ->integerNode('base')->info('The seconds of the first lock, doubled at each further failure.')->defaultValue(60)->min(1)->end()
                    ->integerNode('max')->info('The longest lock, in seconds.')->defaultValue(3600)->min(1)->end()
                ->end()
            ->end()
            ->arrayNode('login_limiter')
                ->info('The limiter of the firewall\'s login_throttling, its option "limiter: phil_identity_provider.login_limiter": per address, per identifier and address, and per identifier whatever the address.')
                ->canBeEnabled()
                ->children()
                    ->integerNode('max_attempts')->info('The sign-ins per identifier and address in each interval.')->defaultValue(5)->min(1)->end()
                    ->integerNode('network_max_attempts')->info('The sign-ins per address, or IPv6 /64 network, in each interval, whatever the identifier.')->defaultValue(25)->min(1)->end()
                    ->integerNode('account_max_attempts')->info('The sign-ins per identifier in each interval, whatever the address: against guessing spread over many addresses. Anyone who knows the identifier can spend it.')->defaultValue(50)->min(1)->end()
                    ->scalarNode('interval')->info('The window of the counts, in the format of the Rate Limiter component.')->defaultValue('15 minutes')->cannotBeEmpty()->end()
                    ->scalarNode('cache_pool')->info('The cache pool of the counts; shared by every server, as Redis or a database, or each server counts on its own.')->defaultValue('cache.rate_limiter')->cannotBeEmpty()->end()
                    ->scalarNode('lock_factory')->info('A LockFactory service on a shared store, so that two sign-ins at once both count; null counts without a lock.')->defaultNull()->end()
                    ->booleanNode('single_server')->info('Accepts a cache pool local to the server in the prod environment, for an application on one server.')->defaultFalse()->end()
                ->end()
            ->end()
            ->arrayNode('links')
                ->addDefaultsIfNotSet()
                ->children()
                    ->integerNode('verify_lifetime')->info('The seconds a link that verifies an address lasts.')->defaultValue(86_400)->min(60)->end()
                    ->integerNode('reset_lifetime')->info('The seconds a link that resets a password lasts.')->defaultValue(3_600)->min(60)->end()
                    ->enumNode('mode')->info('"signed": nothing stored, a link works until it expires or its account changes; "stored": a random token whose keyed hash is stored, used once and revocable, in the table phil_identity_link_token.')->values([IdentityProviderServices::LINKS_SIGNED, IdentityProviderServices::LINKS_STORED])->defaultValue(IdentityProviderServices::LINKS_SIGNED)->end()
                ->end()
            ->end()
            ->arrayNode('passkeys')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('rp_id')->info('The relying party identifier: the domain of the site, such as example.com.')->defaultNull()->end()
                    ->scalarNode('rp_name')->info('The name the authenticator shows; the site name when null.')->defaultNull()->end()
                    ->arrayNode('origins')->info('The origins the pages are served from, such as https://example.com.')->scalarPrototype()->cannotBeEmpty()->end()->end()
                    ->booleanNode('require_user_verification')->info('Whether a passkey must verify the person by a PIN or a biometric.')->defaultFalse()->end()
                    ->arrayNode('attestation')
                        ->info('What is asked of the authenticators of new passkeys. Their AAGUID and backup flags are recorded whatever these options.')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->enumNode('conveyance')->info('The attestation asked of the browser: "none" reads no statement; "indirect", "direct" or "enterprise" read the statements of the format packed.')->values(AttestationPolicy::CONVEYANCES)->defaultValue('none')->end()
                            ->arrayNode('trusted_roots')->info('The PEM files of the root certificates a packed statement may chain to, read when the container is built.')->scalarPrototype()->cannotBeEmpty()->end()->end()
                            ->booleanNode('require_trusted')->info('Whether a passkey whose statement reaches none of the trusted roots is refused; needs trusted_roots and a conveyance other than none.')->defaultFalse()->end()
                            ->arrayNode('allowed_aaguids')->info('The AAGUIDs accepted, empty for any. Without require_trusted, an AAGUID is what the authenticator claims.')->scalarPrototype()->end()->end()
                            ->arrayNode('denied_aaguids')->info('The AAGUIDs refused.')->scalarPrototype()->end()->end()
                            ->enumNode('backup')->info('Whether a passkey that may be synced to other devices is "allowed", "required" or "forbidden".')->values(AttestationPolicy::BACKUP)->defaultValue('allowed')->end()
                        ->end()
                    ->end()
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
                    ->scalarNode('sender')->info('Service id of an SmsSender; a texter on the SMS transports of the Notifier component, without a bus, when null.')->defaultNull()->end()
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
                    ->integerNode('idle_timeout')->info('The seconds without a request after which a session is closed, longer than touch_interval; 0 for no bound.')->defaultValue(7200)->min(0)->end()
                    ->integerNode('max_age')->info('The seconds after it opened past which a session is closed, whatever its activity, at least 60; 0 for no bound.')->defaultValue(43200)->min(0)->end()
                ->end()
                ->validate()
                    ->ifTrue(static fn (array $sessions): bool => 0 !== $sessions['idle_timeout'] && $sessions['idle_timeout'] <= max(59, $sessions['touch_interval']))
                    ->thenInvalid('sessions.idle_timeout is 0, or at least 60 seconds and longer than sessions.touch_interval.')
                ->end()
                ->validate()
                    ->ifTrue(static fn (array $sessions): bool => 0 !== $sessions['max_age'] && $sessions['max_age'] < 60)
                    ->thenInvalid('sessions.max_age is 0, or at least 60 seconds.')
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
                    ->scalarNode('lock_factory')->info('The LockFactory service that serializes the first signing key and each rotation; its store must be shared by every server.')->defaultValue('lock.factory')->cannotBeEmpty()->end()
                    ->arrayNode('signing_keys')
                        ->info('Where the signing keys come from.')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->enumNode('source')->info('"database": keys made, sealed under secrets.kms_key and rotated by the bundle; "file": the key files of "files"; "service": the service of "service", such as the adapter of a hardware security module.')->values([IdentityProviderServices::KEYS_DATABASE, IdentityProviderServices::KEYS_FILE, IdentityProviderServices::KEYS_SERVICE])->defaultValue(IdentityProviderServices::KEYS_DATABASE)->end()
                            ->booleanNode('automatic_rotation')->info('Whether phil:identity-provider:rotate-keys rotates the keys of the database when the schedule runs it; false leaves them until it is run with --force.')->defaultTrue()->end()
                            ->arrayNode('files')
                                ->info('The key files of the source "file", the key that signs first, the others published for the tokens they signed.')
                                ->arrayPrototype()
                                    ->beforeNormalization()->ifString()->then(static fn (string $path): array => ['path' => $path])->end()
                                    ->children()
                                        ->scalarNode('path')->isRequired()->cannotBeEmpty()->end()
                                        ->scalarNode('kid')->info('The key identifier; the JWK thumbprint of the public key when null.')->defaultNull()->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->booleanNode('sealed')->info('Whether the key files are sealed under secrets.kms_key, by phil:identity-provider:seal-key.')->defaultFalse()->end()
                            ->scalarNode('service')->info('The id of the service implementing SigningKeySource, for the source "service".')->defaultNull()->end()
                        ->end()
                    ->end()
                    ->arrayNode('extra_scopes')
                        ->info('Scopes besides openid, profile, email, phone and offline_access that clients may register, such as the scopes of a resource asked by the client_credentials grant.')
                        ->scalarPrototype()
                            ->validate()->ifTrue(static fn (mixed $scope): bool => !\is_string($scope) || 1 !== preg_match('/^[\x21\x23-\x5b\x5d-\x7e]{1,64}$/D', $scope) || \in_array($scope, ProviderSettings::SCOPES, true))->thenInvalid('A scope is 1 to 64 printable characters without a space, a quote or a backslash, and not one the provider knows already: %s.')->end()
                        ->end()
                    ->end()
                    ->arrayNode('client_authentication')
                        ->info('How clients may authenticate at the token, revocation and introspection endpoints.')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->arrayNode('methods')
                                ->info('The methods accepted; leave out client_secret_basic and client_secret_post to accept only client assertions (private_key_jwt) and public clients.')
                                ->defaultValue(ClientAuthMethod::values())
                                ->requiresAtLeastOneElement()
                                ->enumPrototype()->values(ClientAuthMethod::values())->end()
                            ->end()
                            ->integerNode('clock_skew')->info('The seconds a time of a client assertion may be off.')->defaultValue(60)->min(0)->max(300)->end()
                            ->integerNode('assertion_max_lifetime')->info('The furthest ahead, in seconds, the expiry of a client assertion may be; its jti is kept that long.')->defaultValue(300)->min(1)->max(3600)->end()
                            ->booleanNode('legacy_assertion_audiences')->info('Whether a client assertion may also name the token endpoint, and its audiences in an array; off, its aud is the issuer, as a string (draft-ietf-oauth-rfc7523bis).')->defaultFalse()->end()
                        ->end()
                    ->end()
                    ->booleanNode('require_nonce_without_pkce')->info('Whether an authorization request without a PKCE challenge, from a client registered with --pkce-optional, must carry a nonce (RFC 9700, section 2.1.1).')->defaultTrue()->end()
                    ->booleanNode('refresh_token_bound_to_session')->info('Whether a refresh token issued in a session is refused once the session is closed or older than session_max_age, its family revoked.')->defaultTrue()->end()
                    ->integerNode('session_max_age')->info('The seconds after a session opened past which its refresh tokens are no longer issued nor accepted; null for no bound but the end of the session.')->defaultNull()->min(1)->end()
                    ->arrayNode('id_token')
                        ->info('The optional claims of the ID token.')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('acr')->info('The authentication context class reference of every ID token, such as a level the network agreed on; null for none.')->defaultNull()->end()
                            ->scalarNode('idp')->info('The identity provider every ID token names, such as the identifier of the network; null for none.')->defaultNull()->end()
                            ->booleanNode('session_start')->info('Whether the ID token carries session_start, the time its session opened.')->defaultFalse()->end()
                        ->end()
                    ->end()
                    ->arrayNode('subject')
                        ->info('The sub of an account for its clients.')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->enumNode('type')->info('"account" for the internal identifier, "public" for an opaque identifier the same for every client, "pairwise" for one per sector of client.')->values(array_map(static fn (SubjectType $type): string => $type->value, SubjectType::cases()))->defaultValue(SubjectType::Account->value)->end()
                            ->scalarNode('salt')->info('The secret, at least 32 bytes, from the secrets vault, that opaque and pairwise subjects are derived under; it never changes once clients have seen subjects.')->defaultNull()->end()
                        ->end()
                    ->end()
                    ->arrayNode('backchannel_logout')
                        ->info('Logout tokens posted to the clients that registered a back-channel logout address when a session they have tokens of ends, through Messenger; needs symfony/messenger and symfony/http-client.')
                        ->canBeEnabled()
                        ->children()
                            ->scalarNode('bus')->info('The message bus of the logout tokens; route SendLogoutToken to an asynchronous transport, whose retries try a client again.')->defaultValue('messenger.default_bus')->cannotBeEmpty()->end()
                            ->scalarNode('http_client')->info('The HTTP client that posts the logout tokens.')->defaultValue('http_client')->cannotBeEmpty()->end()
                            ->integerNode('timeout')->info('The seconds a client has to answer.')->defaultValue(5)->min(1)->max(30)->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('secrets')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('kms_key')->info('The key of the KeyManagement component that encrypts TOTP secrets and signing keys; null is refused with the modules "totp" or "oidc" unless allow_plaintext_secrets is true.')->defaultNull()->end()
                    ->booleanNode('allow_plaintext_secrets')->info('Whether TOTP secrets and signing keys may be stored in clear when no kms_key is set; the database access controls are then their only protection.')->defaultFalse()->end()
                    ->scalarNode('encrypter')->info('Service id of the encrypter of the KeyManagement component.')->defaultValue('key_management.encrypter')->cannotBeEmpty()->end()
                    ->scalarNode('decrypter')->info('Service id of the decrypter of the KeyManagement component.')->defaultValue('key_management.decrypter')->cannotBeEmpty()->end()
                ->end()
            ->end()
            ->scalarNode('audit')->info('"auto" writes to phil/audit-bundle when it is installed, "none" nowhere, or the id of an AuditRecorder.')->defaultValue('auto')->cannotBeEmpty()->end()
        ->end();
    }

    /**
     * Refuses to boot, in the `prod` environment, when the links sent by email would take the host of the request: no
     * `public_origin` and no `framework.trusted_hosts`. The origin is read here, once its environment variable is known.
     */
    #[Override]
    public function boot(): void
    {
        // Unset until the kernel gives the container, which it does before booting a bundle.
        $container = $this->container ?? null;

        if (!$container instanceof ContainerInterface || true !== $container->getParameter('phil_identity_provider.enabled')) {
            return;
        }

        $origin = $container->getParameter(IdentityProviderServices::PUBLIC_ORIGIN);
        $origin = \is_string($origin) && '' !== $origin ? $origin : null;

        $environment = $container->getParameter('kernel.environment');
        RouterLinkOrigin::origin($origin, true === $container->getParameter('kernel.debug'));
        RouterLinkOrigin::assertBound($origin, \is_string($environment) ? $environment : '', Request::getTrustedHosts());
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new KitSubdirectoryPass());
        $container->addCompilerPass(new LoginLinkPostOnlyPass());
        $container->addCompilerPass(new RateLimitGuard());
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
        /** @phpstan-var array{secret: string, site_name: string, modules: array<string, bool>, store: string, entity_manager: string, clock: string, layout: string, kit_subdirectory: ?string, accounts: array{id_generator: string, strip_subaddress: bool, canonicalizer: ?string}, registration: array{enabled: bool, rate_limit: array{enabled: bool, per_ip: array{limit: positive-int, interval: string}, per_address: array{limit: positive-int, interval: string}, resend_delay: string}}, public_origin: ?string, require_verified_email: bool, login_route: ?string, default_target: string, open_routes: list<string>, mailer: array{from: ?string}, password: array{min_score: int<0, 4>, breach_check: bool, http_client: string}, lockout: array{enabled: bool, threshold: positive-int, base: positive-int, max: positive-int}, login_limiter: array{enabled: bool, max_attempts: positive-int, network_max_attempts: positive-int, account_max_attempts: positive-int, interval: string, cache_pool: string, lock_factory: ?string, single_server: bool}, links: array{verify_lifetime: positive-int, reset_lifetime: positive-int, mode: string}, passkeys: array{rp_id: ?string, rp_name: ?string, origins: list<string>, require_user_verification: bool, attestation: array{conveyance: string, trusted_roots: list<string>, require_trusted: bool, allowed_aaguids: list<string>, denied_aaguids: list<string>, backup: string}}, totp: array{issuer: ?string, window: int<0, 10>}, sms: array{sender: ?string, from: string, message: string, lifetime: positive-int, attempts: positive-int}, step_up: array{attribute: string, lifetime: positive-int}, sessions: array{touch_interval: non-negative-int, idle_timeout: non-negative-int, max_age: non-negative-int}, external: array{link_verified_email: bool, register: bool}, oidc: array{issuer: ?string, algorithm: string, code_lifetime: positive-int, access_token_lifetime: positive-int, id_token_lifetime: positive-int, refresh_token_lifetime: positive-int, key_activation_delay: non-negative-int, retired_key_retention: non-negative-int, allow_localhost: bool, lock_factory: string, signing_keys: array{source: string, automatic_rotation: bool, files: list<array{path: string, kid: ?string}>, sealed: bool, service: ?string}, extra_scopes: list<string>, client_authentication: array{methods: non-empty-list<string>, clock_skew: int<0, 300>, assertion_max_lifetime: int<1, 3600>, legacy_assertion_audiences: bool}, require_nonce_without_pkce: bool, refresh_token_bound_to_session: bool, session_max_age: ?positive-int, id_token: array{acr: ?string, idp: ?string, session_start: bool}, subject: array{type: string, salt: ?string}, backchannel_logout: array{enabled: bool, bus: string, http_client: string, timeout: positive-int}}, secrets: array{kms_key: ?string, allow_plaintext_secrets: bool, encrypter: string, decrypter: string}, audit: string} $config */
        IdentityProviderServices::register($builder, $config);
    }

    /**
     * A count of the Rate Limiter component: how many in each interval.
     */
    private function limit(string $name, string $info, int $limit, string $interval): ArrayNodeDefinition
    {
        return new ArrayNodeDefinition($name)
            ->info($info)
            ->addDefaultsIfNotSet()
            ->children()
                ->integerNode('limit')->defaultValue($limit)->min(1)->end()
                ->scalarNode('interval')->info('The window of the count, in the format of the Rate Limiter component.')->defaultValue($interval)->cannotBeEmpty()->end()
            ->end();
    }

    /**
     * The modules the raw configurations turn on, read before they are processed, for the Doctrine mappings: the
     * defaults that own a model, then each configuration in order; the stored mode of the links counts as one.
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

            if (\is_array($config) && \is_array($config['links'] ?? null) && \array_key_exists('mode', $config['links'])) {
                $modules[IdentityProviderServices::STORED_LINKS] = IdentityProviderServices::LINKS_STORED === $config['links']['mode'];
            }
        }

        return array_keys(array_filter($modules));
    }
}
