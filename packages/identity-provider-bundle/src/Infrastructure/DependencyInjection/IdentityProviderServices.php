<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\DependencyInjection;

use Phil\AuditBundle\Application\AuditTrail;
use Phil\IdentityProviderBundle\Application\AccountLinks;
use Phil\IdentityProviderBundle\Application\Accounts;
use Phil\IdentityProviderBundle\Application\Contract\AccountMailer;
use Phil\IdentityProviderBundle\Application\Contract\AuditRecorder;
use Phil\IdentityProviderBundle\Application\Contract\ChallengeStore;
use Phil\IdentityProviderBundle\Application\Contract\SecretCipher;
use Phil\IdentityProviderBundle\Application\Contract\SmsSender;
use Phil\IdentityProviderBundle\Application\ExternalIdentities;
use Phil\IdentityProviderBundle\Application\Journal;
use Phil\IdentityProviderBundle\Application\Modules;
use Phil\IdentityProviderBundle\Application\Oidc\Authorization;
use Phil\IdentityProviderBundle\Application\Oidc\ClientRegistry;
use Phil\IdentityProviderBundle\Application\Oidc\ProviderSettings;
use Phil\IdentityProviderBundle\Application\Oidc\SigningKeys;
use Phil\IdentityProviderBundle\Application\Oidc\Tokens;
use Phil\IdentityProviderBundle\Application\Passkeys;
use Phil\IdentityProviderBundle\Application\PasswordRules;
use Phil\IdentityProviderBundle\Application\PhoneVerification;
use Phil\IdentityProviderBundle\Application\SecondFactors;
use Phil\IdentityProviderBundle\Application\Sessions;
use Phil\IdentityProviderBundle\Application\SignedLinks;
use Phil\IdentityProviderBundle\Domain\Contract\AccountRepository;
use Phil\IdentityProviderBundle\Domain\Contract\AccountSessionRepository;
use Phil\IdentityProviderBundle\Domain\Contract\ClientRepository;
use Phil\IdentityProviderBundle\Domain\Contract\GrantRepository;
use Phil\IdentityProviderBundle\Domain\Contract\LinkedIdentityRepository;
use Phil\IdentityProviderBundle\Domain\Contract\PasskeyRepository;
use Phil\IdentityProviderBundle\Domain\Contract\SecondFactorRepository;
use Phil\IdentityProviderBundle\Domain\Contract\SigningKeyRepository;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use Phil\IdentityProviderBundle\Domain\Security\LockoutPolicy;
use Phil\IdentityProviderBundle\Domain\Security\RecoveryCodes;
use Phil\IdentityProviderBundle\Domain\WebAuthn\RelyingParty;
use Phil\IdentityProviderBundle\Infrastructure\Audit\AuditBundleRecorder;
use Phil\IdentityProviderBundle\Infrastructure\Audit\NullAuditRecorder;
use Phil\IdentityProviderBundle\Infrastructure\Console\CreateClientCommand;
use Phil\IdentityProviderBundle\Infrastructure\Console\DisableClientCommand;
use Phil\IdentityProviderBundle\Infrastructure\Console\ListClientsCommand;
use Phil\IdentityProviderBundle\Infrastructure\Console\PurgeCommand;
use Phil\IdentityProviderBundle\Infrastructure\Console\RotateKeysCommand;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineAccountRepository;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineAccountSessionRepository;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineClientRepository;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineGrantRepository;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineLinkedIdentityRepository;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrinePasskeyRepository;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineSecondFactorRepository;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineSigningKeyRepository;
use Phil\IdentityProviderBundle\Infrastructure\Federation\ExternalProviders;
use Phil\IdentityProviderBundle\Infrastructure\Http\AccountController;
use Phil\IdentityProviderBundle\Infrastructure\Http\AuthenticatorRouteController;
use Phil\IdentityProviderBundle\Infrastructure\Http\ExternalController;
use Phil\IdentityProviderBundle\Infrastructure\Http\LoginLinkController;
use Phil\IdentityProviderBundle\Infrastructure\Http\Oidc\AuthorizeController;
use Phil\IdentityProviderBundle\Infrastructure\Http\Oidc\EndSessionController;
use Phil\IdentityProviderBundle\Infrastructure\Http\Oidc\MetadataController;
use Phil\IdentityProviderBundle\Infrastructure\Http\Oidc\TokenController;
use Phil\IdentityProviderBundle\Infrastructure\Http\Oidc\UserinfoController;
use Phil\IdentityProviderBundle\Infrastructure\Http\Pages;
use Phil\IdentityProviderBundle\Infrastructure\Http\PasskeyController;
use Phil\IdentityProviderBundle\Infrastructure\Http\PhoneController;
use Phil\IdentityProviderBundle\Infrastructure\Http\SessionController;
use Phil\IdentityProviderBundle\Infrastructure\Http\StepUpController;
use Phil\IdentityProviderBundle\Infrastructure\Http\TotpController;
use Phil\IdentityProviderBundle\Infrastructure\Mailer\MailerAccountMailer;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryAccountRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryAccountSessionRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryClientRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryGrantRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryLinkedIdentityRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryPasskeyRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemorySecondFactorRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemorySigningKeyRepository;
use Phil\IdentityProviderBundle\Infrastructure\Notifier\NotifierSmsSender;
use Phil\IdentityProviderBundle\Infrastructure\Routing\RouteLoader;
use Phil\IdentityProviderBundle\Infrastructure\Secret\KeyManagementSecretCipher;
use Phil\IdentityProviderBundle\Infrastructure\Secret\PlainSecretCipher;
use Phil\IdentityProviderBundle\Infrastructure\Security\AccountUserChecker;
use Phil\IdentityProviderBundle\Infrastructure\Security\AccountUserProvider;
use Phil\IdentityProviderBundle\Infrastructure\Security\ExternalAuthenticator;
use Phil\IdentityProviderBundle\Infrastructure\Security\OneTimeCodeAuthenticator;
use Phil\IdentityProviderBundle\Infrastructure\Security\PasskeyAuthenticator;
use Phil\IdentityProviderBundle\Infrastructure\Security\PasswordSignInListener;
use Phil\IdentityProviderBundle\Infrastructure\Security\ReAuthenticationEntryPoint;
use Phil\IdentityProviderBundle\Infrastructure\Security\SecondFactorListener;
use Phil\IdentityProviderBundle\Infrastructure\Security\SessionChallengeStore;
use Phil\IdentityProviderBundle\Infrastructure\Security\SessionListener;
use Phil\IdentityProviderBundle\Infrastructure\Security\StepUpVoter;
use Phil\IdentityProviderBundle\Infrastructure\Twig\IdentityExtension;
use Phil\IdentityProviderBundle\Infrastructure\Twig\KitComponents;
use Phil\Passwords\Breach\Adapter\PwnedPasswordsSource;
use Phil\Passwords\Breach\BreachChecker;
use Phil\Passwords\Strength\Score;
use Phil\Passwords\Strength\StrengthEstimator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Notifier\TexterInterface;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

/**
 * The services of the configuration, module by module: a module off registers nothing of its own.
 *
 * @phpstan-type Config array{
 *     secret: string,
 *     site_name: string,
 *     modules: array<string, bool>,
 *     store: string,
 *     entity_manager: string,
 *     clock: string,
 *     layout: string,
 *     kit_subdirectory: ?string,
 *     registration: bool,
 *     require_verified_email: bool,
 *     login_route: ?string,
 *     default_target: string,
 *     open_routes: list<string>,
 *     mailer: array{from: ?string},
 *     password: array{min_score: int<0, 4>, breach_check: bool, http_client: string},
 *     lockout: array{threshold: positive-int, base: positive-int, max: positive-int},
 *     links: array{verify_lifetime: positive-int, reset_lifetime: positive-int},
 *     passkeys: array{rp_id: ?string, rp_name: ?string, origins: list<string>, require_user_verification: bool},
 *     totp: array{issuer: ?string, window: int<0, 10>},
 *     sms: array{sender: ?string, from: string, message: string, lifetime: positive-int, attempts: positive-int},
 *     step_up: array{attribute: string, lifetime: positive-int},
 *     sessions: array{touch_interval: non-negative-int},
 *     external: array{link_verified_email: bool, register: bool},
 *     oidc: array{issuer: ?string, algorithm: string, code_lifetime: positive-int, access_token_lifetime: positive-int, id_token_lifetime: positive-int, refresh_token_lifetime: positive-int, key_activation_delay: non-negative-int, retired_key_retention: non-negative-int, allow_localhost: bool},
 *     secrets: array{kms_key: ?string, encrypter: string, decrypter: string},
 *     audit: string,
 * }
 */
final class IdentityProviderServices
{
    public const string STORE_DOCTRINE = 'doctrine';

    public const string STORE_MEMORY = 'memory';

    public const string USER_PROVIDER = 'phil_identity_provider.user_provider';

    public const string USER_CHECKER = 'phil_identity_provider.user_checker';

    public const string RE_AUTHENTICATION_ENTRY_POINT = 'phil_identity_provider.re_authentication_entry_point';

    /** The stores of each module, with their Doctrine and memory implementations. */
    private const array REPOSITORIES = [
        Modules::ACCOUNTS => [AccountRepository::class => [DoctrineAccountRepository::class, InMemoryAccountRepository::class]],
        Modules::PASSKEYS => [PasskeyRepository::class => [DoctrinePasskeyRepository::class, InMemoryPasskeyRepository::class]],
        Modules::TOTP => [SecondFactorRepository::class => [DoctrineSecondFactorRepository::class, InMemorySecondFactorRepository::class]],
        Modules::SESSIONS => [AccountSessionRepository::class => [DoctrineAccountSessionRepository::class, InMemoryAccountSessionRepository::class]],
        Modules::EXTERNAL => [LinkedIdentityRepository::class => [DoctrineLinkedIdentityRepository::class, InMemoryLinkedIdentityRepository::class]],
        Modules::OIDC => [
            ClientRepository::class => [DoctrineClientRepository::class, InMemoryClientRepository::class],
            GrantRepository::class => [DoctrineGrantRepository::class, InMemoryGrantRepository::class],
            SigningKeyRepository::class => [DoctrineSigningKeyRepository::class, InMemorySigningKeyRepository::class],
        ],
    ];

    /** The directory of the models of each module, under `src/Domain/Model`, which Doctrine maps when it is on. */
    public const array MODEL_DIRECTORIES = [
        Modules::ACCOUNTS => 'Account',
        Modules::PASSKEYS => 'Passkey',
        Modules::TOTP => 'Totp',
        Modules::SESSIONS => 'Session',
        Modules::EXTERNAL => 'External',
        Modules::OIDC => 'Oidc',
    ];

    private function __construct()
    {
    }

    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $modules = array_keys(array_filter($config['modules']));
        self::assertModules($modules, $config);
        $clock = new Reference($config['clock']);

        $container->register(Modules::class, Modules::class)->setArguments([$modules]);
        self::stores($container, $config, $modules);
        self::common($container, $config, $clock);
        self::security($container, $config, $modules, $clock);
        self::methods($container, $config, $modules, $clock);

        if (\in_array(Modules::OIDC, $modules, true)) {
            self::oidc($container, $config, $clock);
        }

        self::pages($container, $config, $modules);
    }

    /**
     * @param list<string> $modules
     * @param Config       $config
     */
    private static function assertModules(array $modules, array $config): void
    {
        if (!\in_array(Modules::ACCOUNTS, $modules, true)) {
            throw new InvalidConfigurationException('The module "accounts" holds the accounts every other module works on: it cannot be turned off.');
        }

        if (\in_array(Modules::PASSKEYS, $modules, true) && (null === $config['passkeys']['rp_id'] || [] === $config['passkeys']['origins'])) {
            throw new InvalidConfigurationException('The module "passkeys" needs "passkeys.rp_id", the domain of the site, and "passkeys.origins", the addresses its pages are served from.');
        }

        if (\in_array(Modules::OIDC, $modules, true) && null === $config['oidc']['issuer']) {
            throw new InvalidConfigurationException('The module "oidc" needs "oidc.issuer", the https address clients know the provider by.');
        }

        if (\in_array(Modules::RECOVERY_CODES, $modules, true) && !\in_array(Modules::TOTP, $modules, true)) {
            throw new InvalidConfigurationException('The module "recovery_codes" stands in for the authenticator application: turn on "totp" too.');
        }
    }

    /**
     * @param Config       $config
     * @param list<string> $modules
     */
    private static function stores(ContainerBuilder $container, array $config, array $modules): void
    {
        foreach (self::REPOSITORIES as $module => $repositories) {
            if (!\in_array($module, $modules, true)) {
                continue;
            }

            foreach ($repositories as $interface => [$doctrine, $memory]) {
                if (self::STORE_MEMORY === $config['store']) {
                    $container->register($interface, $memory)->addTag('kernel.reset', ['method' => 'reset'])->setPublic(true);
                } elseif (self::STORE_DOCTRINE === $config['store']) {
                    $container->register($interface, $doctrine)
                        ->setArguments([new Reference(\sprintf('doctrine.orm.%s_entity_manager', $config['entity_manager']))])
                        ->setPublic(true);
                } else {
                    throw new InvalidConfigurationException(\sprintf('The store is "doctrine" or "memory", not "%s".', $config['store']));
                }
            }
        }
    }

    /**
     * @param Config $config
     */
    private static function common(ContainerBuilder $container, array $config, Reference $clock): void
    {
        if ('none' === $config['audit']) {
            $container->register(AuditRecorder::class, NullAuditRecorder::class);
        } elseif ('auto' === $config['audit']) {
            $container->register(AuditRecorder::class, class_exists(AuditTrail::class) && $container->hasDefinition(AuditTrail::class) ? AuditBundleRecorder::class : NullAuditRecorder::class)
                ->setArguments(class_exists(AuditTrail::class) && $container->hasDefinition(AuditTrail::class) ? [new Reference(AuditTrail::class)] : []);
        } else {
            $container->setAlias(AuditRecorder::class, $config['audit']);
        }

        $container->register(Journal::class, Journal::class)
            ->setArguments([new Reference(AuditRecorder::class), new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);

        if (null !== $config['secrets']['kms_key'] && interface_exists(EncrypterInterface::class)) {
            $container->register(SecretCipher::class, KeyManagementSecretCipher::class)
                ->setArguments([new Reference($config['secrets']['encrypter']), new Reference($config['secrets']['decrypter']), $config['secrets']['kms_key']]);
        } else {
            $container->register(SecretCipher::class, PlainSecretCipher::class);
        }

        $container->register(ChallengeStore::class, SessionChallengeStore::class)->setArguments([new Reference('request_stack')]);

        if (null !== $config['mailer']['from'] && interface_exists(MailerInterface::class)) {
            $container->register(AccountMailer::class, MailerAccountMailer::class)
                ->setArguments([new Reference('mailer'), new Reference('translator'), $config['mailer']['from'], $config['site_name']]);
        }

        $mailer = new Reference(AccountMailer::class, ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $container->register(StrengthEstimator::class.' $phil_identity_provider', StrengthEstimator::class);
        $breaches = null;

        if ($config['password']['breach_check']) {
            $breaches = new Definition(BreachChecker::class, [new Definition(PwnedPasswordsSource::class, [new Reference($config['password']['http_client'])])]);
        }

        $container->register(PasswordRules::class, PasswordRules::class)
            ->setArguments([new Reference(StrengthEstimator::class.' $phil_identity_provider'), Score::from($config['password']['min_score']), $breaches]);
        $container->register(LockoutPolicy::class, LockoutPolicy::class)
            ->setArguments([$config['lockout']['threshold'], $config['lockout']['base'], $config['lockout']['max']]);
        $container->register(Accounts::class, Accounts::class)
            ->setArguments([new Reference(AccountRepository::class), new Reference('security.user_password_hasher'), new Reference(PasswordRules::class), new Reference(Journal::class), $clock, new Reference(LockoutPolicy::class)])
            ->setPublic(true);
        $container->register(SignedLinks::class, SignedLinks::class)
            ->setArguments([$config['secret'], $clock, $config['links']['verify_lifetime'], $config['links']['reset_lifetime']]);
        $container->register(AccountLinks::class, AccountLinks::class)
            ->setArguments([new Reference(SignedLinks::class), new Reference('router'), new Reference(AccountRepository::class), new Reference(Journal::class), $mailer])
            ->setPublic(true);
    }

    /**
     * @param Config       $config
     * @param list<string> $modules
     */
    private static function security(ContainerBuilder $container, array $config, array $modules, Reference $clock): void
    {
        $container->register(self::USER_PROVIDER, AccountUserProvider::class)->setArguments([new Reference(AccountRepository::class)]);
        $container->setAlias(AccountUserProvider::class, self::USER_PROVIDER);
        $container->register(self::USER_CHECKER, AccountUserChecker::class)->setArguments([$config['require_verified_email']]);
        $container->setAlias(AccountUserChecker::class, self::USER_CHECKER);
        $container->register(PasswordSignInListener::class, PasswordSignInListener::class)
            ->setArguments([new Reference(Accounts::class), $clock])
            ->addTag('kernel.event_listener', ['event' => CheckPassportEvent::class, 'method' => 'onCheckPassport', 'priority' => 32])
            ->addTag('kernel.event_listener', ['event' => LoginFailureEvent::class, 'method' => 'onLoginFailure'])
            ->addTag('kernel.event_listener', ['event' => LoginSuccessEvent::class, 'method' => 'onLoginSuccess']);

        if (\in_array(Modules::SESSIONS, $modules, true)) {
            $container->register(Sessions::class, Sessions::class)
                ->setArguments([
                    new Reference(AccountSessionRepository::class),
                    new Reference(Journal::class),
                    $clock,
                    new Reference(AccountMailer::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    \in_array(Modules::OIDC, $modules, true) ? new Reference(GrantRepository::class) : null,
                    $config['sessions']['touch_interval'],
                ])
                ->setPublic(true);
            $container->register(SessionListener::class, SessionListener::class)
                ->setArguments([new Reference(Sessions::class), new Reference('security.token_storage')])
                ->addTag('kernel.event_listener', ['event' => KernelEvents::REQUEST, 'method' => 'onRequest', 'priority' => 7])
                ->addTag('kernel.event_listener', ['event' => LoginSuccessEvent::class, 'method' => 'onLoginSuccess', 'priority' => -16])
                ->addTag('kernel.event_listener', ['event' => LogoutEvent::class, 'method' => 'onLogout']);
        }

        if (\in_array(Modules::STEP_UP, $modules, true)) {
            $container->register(StepUpVoter::class, StepUpVoter::class)
                ->setArguments([
                    $clock,
                    new Reference(SecondFactors::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference(Passkeys::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    $config['step_up']['lifetime'],
                ])
                ->addTag('security.voter');
            $container->register(self::RE_AUTHENTICATION_ENTRY_POINT, ReAuthenticationEntryPoint::class)->setArguments([new Reference('router')]);
            $container->setAlias(ReAuthenticationEntryPoint::class, self::RE_AUTHENTICATION_ENTRY_POINT);
        }
    }

    /**
     * @param Config       $config
     * @param list<string> $modules
     */
    private static function methods(ContainerBuilder $container, array $config, array $modules, Reference $clock): void
    {
        $journal = new Reference(Journal::class);

        if (\in_array(Modules::PASSKEYS, $modules, true)) {
            $container->register(RelyingParty::class, RelyingParty::class)
                ->setArguments([(string) $config['passkeys']['rp_id'], $config['passkeys']['rp_name'] ?? $config['site_name'], $config['passkeys']['origins'], $config['passkeys']['require_user_verification']]);
            $container->register(Passkeys::class, Passkeys::class)
                ->setArguments([new Reference(RelyingParty::class), new Reference(PasskeyRepository::class), new Reference(AccountRepository::class), new Reference(ChallengeStore::class), $journal, $clock])
                ->setPublic(true);
            $container->register(PasskeyAuthenticator::class, PasskeyAuthenticator::class)
                ->setArguments([new Reference(Passkeys::class), new Reference('security.token_storage'), $config['default_target']]);
        }

        if (\in_array(Modules::TOTP, $modules, true)) {
            $container->register(RecoveryCodes::class, RecoveryCodes::class)->setArguments([hash_hmac('sha256', 'phil_identity_provider.recovery_codes', $config['secret'])]);
            $container->register(SecondFactors::class, SecondFactors::class)
                ->setArguments([new Reference(SecondFactorRepository::class), new Reference(SecretCipher::class), new Reference(RecoveryCodes::class), $journal, $clock, $config['totp']['issuer'] ?? $config['site_name'], $config['totp']['window']])
                ->setPublic(true);
            $container->register(OneTimeCodeAuthenticator::class, OneTimeCodeAuthenticator::class)
                ->setArguments([new Reference('security.token_storage'), new Reference(SecondFactors::class), new Reference('router'), $config['default_target']]);
            $container->register(SecondFactorListener::class, SecondFactorListener::class)
                ->setArguments([new Reference('security.token_storage'), new Reference(SecondFactors::class), new Reference('router'), $config['open_routes']])
                ->addTag('kernel.event_listener', ['event' => KernelEvents::REQUEST, 'method' => 'onRequest', 'priority' => 6]);
        }

        if (\in_array(Modules::SMS, $modules, true)) {
            if (null !== $config['sms']['sender']) {
                $container->setAlias(SmsSender::class, $config['sms']['sender']);
            } elseif (interface_exists(TexterInterface::class)) {
                $container->register(SmsSender::class, NotifierSmsSender::class)->setArguments([new Reference('texter'), $config['sms']['from']]);
            } else {
                throw new InvalidConfigurationException('The module "sms" needs the Notifier component and its texter, or "sms.sender", a service implementing SmsSender.');
            }

            $container->register(PhoneVerification::class, PhoneVerification::class)
                ->setArguments([new Reference(SmsSender::class), new Reference(ChallengeStore::class), new Reference(AccountRepository::class), $journal, $clock, $config['sms']['message'], $config['sms']['lifetime'], $config['sms']['attempts']])
                ->setPublic(true);
        }

        if (\in_array(Modules::EXTERNAL, $modules, true)) {
            $container->register(ExternalProviders::class, ExternalProviders::class)->setArguments([new TaggedIteratorArgument('phil_identity_provider.external_provider')]);
            $container->register(ExternalIdentities::class, ExternalIdentities::class)
                ->setArguments([new Reference(LinkedIdentityRepository::class), new Reference(AccountRepository::class), new Reference(PasskeyRepository::class, ContainerInterface::NULL_ON_INVALID_REFERENCE), new Reference(Accounts::class), $journal, $clock, $config['external']['link_verified_email'], $config['external']['register']])
                ->setPublic(true);
            $container->register(ExternalAuthenticator::class, ExternalAuthenticator::class)
                ->setArguments([new Reference(ExternalProviders::class), new Reference(ExternalIdentities::class), new Reference('security.token_storage'), new Reference('router'), $config['default_target'], $config['login_route']]);
        }
    }

    /**
     * @param Config $config
     */
    private static function oidc(ContainerBuilder $container, array $config, Reference $clock): void
    {
        $journal = new Reference(Journal::class);
        $container->register(ProviderSettings::class, ProviderSettings::class)->setArguments([
            $config['oidc']['issuer'],
            SigningAlgorithm::from($config['oidc']['algorithm']),
            $config['oidc']['code_lifetime'],
            $config['oidc']['access_token_lifetime'],
            $config['oidc']['id_token_lifetime'],
            $config['oidc']['refresh_token_lifetime'],
            ProviderSettings::SCOPES,
            $config['oidc']['key_activation_delay'],
            $config['oidc']['retired_key_retention'],
        ]);
        $container->register(SigningKeys::class, SigningKeys::class)
            ->setArguments([new Reference(SigningKeyRepository::class), new Reference(SecretCipher::class), new Reference(ProviderSettings::class), $clock, $journal])
            ->setPublic(true);
        $container->register(ClientRegistry::class, ClientRegistry::class)
            ->setArguments([new Reference(ClientRepository::class), $clock, new Reference(ProviderSettings::class), $journal, $config['oidc']['allow_localhost']])
            ->setPublic(true);
        $container->register(Authorization::class, Authorization::class)
            ->setArguments([new Reference(ClientRepository::class), new Reference(GrantRepository::class), new Reference(ProviderSettings::class), $clock, $journal])
            ->setPublic(true);
        $container->register(Tokens::class, Tokens::class)
            ->setArguments([new Reference(ClientRepository::class), new Reference(GrantRepository::class), new Reference(AccountRepository::class), new Reference(SigningKeys::class), new Reference(ProviderSettings::class), $clock, $journal])
            ->setPublic(true);

        self::controller($container, MetadataController::class, [new Reference(ProviderSettings::class), new Reference(SigningKeys::class), new Reference('router')]);
        self::controller($container, AuthorizeController::class, [new Reference(Authorization::class), new Reference(Pages::class), new Reference('security.token_storage'), new Reference('router'), $clock, $config['oidc']['issuer'], new Reference('security.helper')]);
        self::controller($container, TokenController::class, [new Reference(Tokens::class)]);
        self::controller($container, UserinfoController::class, [new Reference(Tokens::class)]);
        self::controller($container, EndSessionController::class, [new Reference(Tokens::class), new Reference(ClientRegistry::class), new Reference(Pages::class), new Reference('security.helper')]);

        if (class_exists(Command::class)) {
            foreach ([CreateClientCommand::class, ListClientsCommand::class, DisableClientCommand::class] as $command) {
                $container->register($command, $command)->setArguments([new Reference(ClientRegistry::class)])->addTag('console.command');
            }

            $container->register(RotateKeysCommand::class, RotateKeysCommand::class)->setArguments([new Reference(SigningKeys::class)])->addTag('console.command');
        }
    }

    /**
     * @param Config       $config
     * @param list<string> $modules
     */
    private static function pages(ContainerBuilder $container, array $config, array $modules): void
    {
        $container->register(KitComponents::class, KitComponents::class)->setArguments([$config['kit_subdirectory']]);
        $container->register(IdentityExtension::class, IdentityExtension::class)
            ->setArguments([new Reference(KitComponents::class), new Reference(Modules::class)])
            ->addTag('twig.attribute_extension')
            ->addTag('twig.runtime');
        $container->register(RouteLoader::class, RouteLoader::class)->setArguments([new Reference(Modules::class), '%kernel.environment%'])->addTag('routing.loader');
        $container->register(Pages::class, Pages::class)->setArguments([
            new Reference('twig'),
            new Reference('security.token_storage'),
            new Reference('security.authorization_checker'),
            new Reference('security.csrf.token_manager'),
            new Reference('router'),
            $config['layout'],
            \in_array(Modules::STEP_UP, $modules, true) ? $config['step_up']['attribute'] : 'IS_AUTHENTICATED_FULLY',
        ]);
        $pages = new Reference(Pages::class);
        $optional = static fn (string $id): Reference => new Reference($id, ContainerInterface::NULL_ON_INVALID_REFERENCE);

        self::controller($container, AuthenticatorRouteController::class, []);
        self::controller($container, AccountController::class, [$pages, new Reference(Accounts::class), new Reference(AccountLinks::class), new Reference(Modules::class), $config['registration']]);

        if (\in_array(Modules::PASSKEYS, $modules, true)) {
            self::controller($container, PasskeyController::class, [$pages, new Reference(Passkeys::class)]);
        }

        if (\in_array(Modules::TOTP, $modules, true)) {
            self::controller($container, TotpController::class, [$pages, new Reference(SecondFactors::class), $optional(Passkeys::class), \in_array(Modules::RECOVERY_CODES, $modules, true)]);
        }

        if (\in_array(Modules::LOGIN_LINK, $modules, true)) {
            self::controller($container, LoginLinkController::class, [$pages, new Reference(AccountRepository::class), new Reference(LoginLinkHandlerInterface::class), new Reference(Journal::class), $optional(AccountMailer::class)]);
        }

        if (\in_array(Modules::SMS, $modules, true)) {
            self::controller($container, PhoneController::class, [$pages, new Reference(PhoneVerification::class)]);
        }

        if (\in_array(Modules::SESSIONS, $modules, true)) {
            self::controller($container, SessionController::class, [$pages, new Reference(Sessions::class), $optional(Authorization::class)]);
        }

        if (\in_array(Modules::STEP_UP, $modules, true)) {
            self::controller($container, StepUpController::class, [$pages, $optional(Passkeys::class), $optional(SecondFactors::class), $config['login_route'], $optional(ExternalIdentities::class)]);
        }

        if (\in_array(Modules::EXTERNAL, $modules, true)) {
            self::controller($container, ExternalController::class, [$pages, new Reference(ExternalProviders::class), new Reference(ExternalIdentities::class), new Reference('router')]);
        }

        if (class_exists(Command::class)) {
            $container->register(PurgeCommand::class, PurgeCommand::class)
                ->setArguments([new Reference($config['clock']), $optional(GrantRepository::class), $optional(AccountSessionRepository::class)])
                ->addTag('console.command');
        }
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function controller(ContainerBuilder $container, string $class, array $arguments): void
    {
        $container->register($class, $class)->setArguments($arguments)->addTag('controller.service_arguments')->setPublic(true);
    }
}
