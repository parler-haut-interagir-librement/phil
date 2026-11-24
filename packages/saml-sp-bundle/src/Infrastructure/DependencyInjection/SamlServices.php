<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Infrastructure\DependencyInjection;

use Phil\SafeHttp\Limits;
use Phil\SafeHttp\SafeHttpClient;
use Phil\SamlSp\Attribute\AttributeMap;
use Phil\SamlSp\Binding\Binding;
use Phil\SamlSp\Contract\ReplayGuard;
use Phil\SamlSp\Http\SafeHttpMetadataFetcher;
use Phil\SamlSp\Message\LogoutProcessor;
use Phil\SamlSp\Message\MessageFactory;
use Phil\SamlSp\Message\ResponseProcessor;
use Phil\SamlSp\Metadata\MetadataLoader;
use Phil\SamlSp\Metadata\MetadataReader;
use Phil\SamlSp\Provider\SecurityPolicy;
use Phil\SamlSp\Signature\SignaturePolicy;
use Phil\SamlSp\Store\CachePendingRequests;
use Phil\SamlSp\Xml\XmlBudget;
use Phil\SamlSpBundle\Application\IdentityProviders;
use Phil\SamlSpBundle\Application\IdentityProviderSettings;
use Phil\SamlSpBundle\Application\SamlLogin;
use Phil\SamlSpBundle\Application\SamlLogout;
use Phil\SamlSpBundle\Application\SamlUsers;
use Phil\SamlSpBundle\Application\ServiceProviderSettings;
use Phil\SamlSpBundle\Infrastructure\Console\CheckCommand;
use Phil\SamlSpBundle\Infrastructure\Controller\SamlController;
use Phil\SamlSpBundle\Infrastructure\Routing\RoutedEndpoints;
use Phil\SamlSpBundle\Infrastructure\Security\SamlAuthenticator;
use Phil\SamlSpBundle\Infrastructure\Security\SamlUserProvider;
use Phil\SamlSpBundle\Infrastructure\Security\SingleLogoutListener;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * The services of the bundle, from its processed configuration.
 *
 * @phpstan-type KeyPem array{certificate: string, private_key: string, passphrase: string|null}
 * @phpstan-type Provider array{metadata_url: ?string, metadata_file: ?string, metadata_xml: ?string, metadata_certificates: list<string>, entity_id: ?string, preset: string, attributes: array<string, list<string>>, group_roles: array<string, string>, default_roles: list<string>, want_assertions_signed: bool, want_assertions_encrypted: bool, allow_unsolicited: bool, binding: string, single_logout: bool, create_accounts: bool, force_authn: bool}
 * @phpstan-type Config array{entity_id: ?string, signing?: KeyPem, encryption: list<KeyPem>, name_id_format: ?string, sign_requests: bool, clock_skew: int, max_age: int, allow_sha1: bool, min_rsa_bits: int, allow_localhost: bool, bind_to_browser: bool, secure_cookie: bool, default_target_path: string, logout_target_path: string, cache_pool: string, replay_guard: array{store: 'auto'|'doctrine'|'cache', connection: string, table: string}, single_server: bool, metadata_lifetime: int, request_lifetime: int, clock: string, http_client: ?string, accounts: ?string, identity_providers: array<string, Provider>}
 */
final readonly class SamlServices
{
    public const string ALIAS = 'phil_saml_sp';

    public const array PRESETS = ['none', 'entra_id', 'okta', 'generic'];

    private function __construct()
    {
    }

    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $clock = new Reference($config['clock']);
        $pool = new Reference($config['cache_pool']);
        $events = new Reference('event_dispatcher');

        $container->register(SecurityPolicy::class, SecurityPolicy::class)->setArguments([
            $config['clock_skew'],
            $config['max_age'],
            new Definition(SignaturePolicy::class, [$config['allow_sha1'], $config['min_rsa_bits']]),
            new Definition(XmlBudget::class),
        ]);
        $limits = new Definition(Limits::class)->setFactory([SafeHttpMetadataFetcher::class, 'defaultLimits']);
        $client = new Definition(SafeHttpClient::class, [null === $config['http_client'] ? null : new Reference($config['http_client']), $limits]);
        $container->register(SafeHttpMetadataFetcher::class, SafeHttpMetadataFetcher::class)->setArguments([$client])->addTag('kernel.reset', ['method' => 'reset']);
        $container->register(MetadataLoader::class, MetadataLoader::class)->setArguments([
            new Reference(SafeHttpMetadataFetcher::class),
            $clock,
            $pool,
            $config['metadata_lifetime'],
            new Definition(MetadataReader::class, [
                new Definition(SignaturePolicy::class, [$config['allow_sha1'], $config['min_rsa_bits']]),
                new Definition(XmlBudget::class, [4 * 1024 * 1024, 48, 200_000]),
                $config['allow_localhost'],
            ]),
        ]);
        $container->register(RoutedEndpoints::class, RoutedEndpoints::class)->setArguments([new Reference('router'), $config['allow_localhost']]);
        $container->register(IdentityProviders::class, IdentityProviders::class)
            ->setArguments([self::settings($config['identity_providers']), self::serviceProvider($config), new Reference(MetadataLoader::class), new Reference(RoutedEndpoints::class)])
            ->addTag('kernel.reset', ['method' => 'reset']);
        $container->register(MessageFactory::class, MessageFactory::class)->setArguments([$clock]);
        $container->setParameter(ReplayGuardPass::PARAMETER, [
            'store' => $config['replay_guard']['store'],
            'connection' => $config['replay_guard']['connection'],
            'table' => $config['replay_guard']['table'],
            'pool' => $config['cache_pool'],
            'clock' => $config['clock'],
            'single_server' => $config['single_server'],
        ]);
        $container->register(CachePendingRequests::class, CachePendingRequests::class)->setArguments([$pool, $config['request_lifetime']]);
        $container->register(ResponseProcessor::class, ResponseProcessor::class)->setArguments([$clock, new Reference(ReplayGuard::class), new Reference(SecurityPolicy::class)]);
        $container->register(LogoutProcessor::class, LogoutProcessor::class)->setArguments([$clock, new Reference(SecurityPolicy::class)]);

        $providers = new Reference(IdentityProviders::class);
        $container->register(SamlLogin::class, SamlLogin::class)->setArguments([$providers, new Reference(MessageFactory::class), new Reference(ResponseProcessor::class), new Reference(CachePendingRequests::class), $clock, $config['default_target_path']]);
        $container->register(SamlLogout::class, SamlLogout::class)->setArguments([$providers, new Reference(MessageFactory::class), new Reference(LogoutProcessor::class), new Reference(CachePendingRequests::class), $clock, $events, $config['logout_target_path']]);
        $container->register(SamlUsers::class, SamlUsers::class)->setArguments([$providers, $events, null === $config['accounts'] ? null : new Reference($config['accounts'])]);

        $container->register(SamlAuthenticator::class, SamlAuthenticator::class)->setArguments([new Reference(SamlLogin::class), new Reference(SamlUsers::class), $events, $config['secure_cookie']]);
        $container->register(SamlUserProvider::class, SamlUserProvider::class);
        $container->setAlias(self::ALIAS.'.user_provider', SamlUserProvider::class)->setPublic(true);
        $container->register(SingleLogoutListener::class, SingleLogoutListener::class)
            ->setArguments([new Reference(SamlLogout::class), $providers, $events])
            ->addTag('kernel.event_listener', ['event' => LogoutEvent::class, 'method' => 'onLogout', 'priority' => 32]);
        $container->register(SamlController::class, SamlController::class)
            ->setArguments([$providers, new Reference(SamlLogin::class), new Reference(SamlLogout::class), $events, new Reference(SecurityPolicy::class), new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE), $config['bind_to_browser'], $config['secure_cookie'], $config['request_lifetime']])
            ->addTag('controller.service_arguments')
            ->setPublic(true);

        if (class_exists(Command::class)) {
            $container->register(CheckCommand::class, CheckCommand::class)->setArguments([$providers, $clock])->addTag('console.command', ['command' => 'phil:saml:check']);
        }
    }

    /**
     * @param array<string, Provider> $providers
     *
     * @return list<Definition>
     */
    private static function settings(array $providers): array
    {
        $settings = [];

        foreach ($providers as $key => $provider) {
            $map = match ($provider['preset']) {
                'entra_id' => AttributeMap::entraId()->names,
                'okta' => AttributeMap::okta()->names,
                'generic' => AttributeMap::generic()->names,
                default => [],
            };

            $settings[] = new Definition(IdentityProviderSettings::class, [
                $key,
                $provider['metadata_url'],
                $provider['metadata_xml'],
                $provider['metadata_file'],
                $provider['metadata_certificates'],
                $provider['entity_id'],
                new Definition(AttributeMap::class, [array_replace($map, $provider['attributes'])]),
                $provider['group_roles'],
                $provider['default_roles'],
                $provider['want_assertions_signed'],
                $provider['want_assertions_encrypted'],
                $provider['allow_unsolicited'],
                'post' === $provider['binding'] ? Binding::Post : Binding::Redirect,
                $provider['single_logout'],
                $provider['create_accounts'],
                $provider['force_authn'],
            ]);
        }

        return $settings;
    }

    /**
     * @param Config $config
     */
    private static function serviceProvider(array $config): Definition
    {
        return new Definition(ServiceProviderSettings::class, [$config['entity_id'], $config['signing'] ?? null, $config['encryption'], $config['name_id_format'], $config['sign_requests']]);
    }
}
