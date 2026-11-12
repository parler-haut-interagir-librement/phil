<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Infrastructure\DependencyInjection;

use Phil\IdentityClientBundle\Application\Contract\RefreshOutcomes;
use Phil\IdentityClientBundle\Application\Contract\ReplayStore;
use Phil\IdentityClientBundle\Application\Contract\ReturnToPolicy;
use Phil\IdentityClientBundle\Application\Contract\UserProvisioner;
use Phil\IdentityClientBundle\Application\Elevation\AssuranceLevel;
use Phil\IdentityClientBundle\Application\Elevation\AssuranceLevels;
use Phil\IdentityClientBundle\Infrastructure\Security\AssuranceChallengeListener;
use Phil\IdentityClientBundle\Infrastructure\Security\AssuranceEvaluator;
use Phil\IdentityClientBundle\Infrastructure\Security\AssuranceVoter;
use Phil\IdentityClientBundle\Infrastructure\Security\AuthorizationRequestListener;
use Phil\IdentityClientBundle\Infrastructure\Security\DeclaredOriginsReturnToPolicy;
use Phil\IdentityClientBundle\Infrastructure\Security\ElevationAttempts;
use Phil\IdentityClientBundle\Infrastructure\Security\EndedSessionListener;
use Phil\IdentityClientBundle\Infrastructure\Security\IdentityUserProvider;
use Phil\IdentityClientBundle\Infrastructure\Security\ReplayGuardedBackChannelLogout;
use Phil\IdentityClientBundle\Infrastructure\Security\SerializedTokenRefreshListener;
use Phil\IdentityClientBundle\Infrastructure\Security\SessionCapListener;
use Phil\IdentityClientBundle\Infrastructure\Security\SignInListener;
use Phil\IdentityClientBundle\Infrastructure\Security\StatelessUserProvisioner;
use Phil\IdentityClientBundle\Infrastructure\Security\TokenAttribute;
use Phil\IdentityClientBundle\Infrastructure\Store\CacheReplayStore;
use Phil\IdentityClientBundle\Infrastructure\Store\EncryptedRefreshOutcomes;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\AuthenticationTokenCreatedEvent;
use Symfony\Component\Security\Http\Event\CheckRefreshedUserEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\OidcAuthorizationRequestEvent;
use Symfony\Component\Security\Http\Event\TokenDeauthenticatedEvent;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * The services of the bundle, wired to those `oidc_login` declares for the firewall.
 *
 * @phpstan-import-type Config from ClientConfiguration
 */
final class ClientServices
{
    public const string NATIVE_AUTHENTICATOR = 'security.authenticator.oidc_login.';

    private const int BEFORE_FIREWALL_EXCEPTION_LISTENER = 2;

    /**
     * @param Config $config
     */
    public static function register(ContainerConfigurator $container, array $config): void
    {
        $services = $container->services();
        $firewall = $config['firewall'];
        $firewallDispatcher = 'security.event_dispatcher.'.$firewall;

        $services->set(StatelessUserProvisioner::class);
        $services->alias(UserProvisioner::class, StatelessUserProvisioner::class);
        $services->set(DeclaredOriginsReturnToPolicy::class)->args([$config['return_to']['origins']]);
        $services->alias(ReturnToPolicy::class, DeclaredOriginsReturnToPolicy::class);

        $services->set(FirewallComposer::USER_PROVIDER_SERVICE, IdentityUserProvider::class)
            ->args([service(UserProvisioner::class), $config['issuer'], $config['claims']]);

        $services->set(AssuranceLevels::class)->args([self::levels($config)]);
        $services->set(AssuranceEvaluator::class)->args([service(AssuranceLevels::class), service('clock')]);
        $services->set(ElevationAttempts::class)->args([
            service(AssuranceLevels::class),
            service(AssuranceEvaluator::class),
            service('event_dispatcher'),
            service('clock'),
            $config['elevation']['retry_after'],
        ]);
        $services->set(AssuranceVoter::class)
            ->args([service(AssuranceLevels::class), service(AssuranceEvaluator::class), service(ElevationAttempts::class), service('request_stack')])
            ->tag('security.voter');
        $services->set(AssuranceChallengeListener::class)
            ->args([service(AssuranceLevels::class), service('security.token_storage')])
            ->tag('kernel.event_listener', ['event' => KernelEvents::EXCEPTION, 'priority' => self::BEFORE_FIREWALL_EXCEPTION_LISTENER]);

        $services->set(AuthorizationRequestListener::class)
            ->args([service(ReturnToPolicy::class), service(AssuranceLevels::class), service(ElevationAttempts::class), service('event_dispatcher'), $config['return_to']['parameter']])
            ->tag('kernel.event_listener', ['event' => OidcAuthorizationRequestEvent::class, 'dispatcher' => $firewallDispatcher]);

        $services->set(SignInListener::class)
            ->args([
                service(self::NATIVE_AUTHENTICATOR.'id_token.'.$firewall),
                service('security.token_storage'),
                service('request_stack'),
                service(ElevationAttempts::class),
                service('event_dispatcher'),
                service('clock'),
                $config['session']['start_claim'],
                self::discardedAttributes($config),
            ])
            ->tag('kernel.event_listener', ['event' => AuthenticationTokenCreatedEvent::class, 'method' => 'onTokenCreated', 'dispatcher' => $firewallDispatcher])
            ->tag('kernel.event_listener', ['event' => LoginFailureEvent::class, 'method' => 'onLoginFailure', 'dispatcher' => $firewallDispatcher]);

        $services->set(EndedSessionListener::class)
            ->args([service('event_dispatcher')])
            ->tag('kernel.event_listener', ['event' => TokenDeauthenticatedEvent::class]);

        if (0 < $config['session']['max_lifetime']) {
            $services->set(SessionCapListener::class)
                ->args([service('clock'), $config['session']['max_lifetime']])
                ->tag('kernel.event_listener', ['event' => CheckRefreshedUserEvent::class]);
        }

        if ($config['back_channel_logout']['enabled']) {
            self::registerBackChannelLogout($services, $config);
        }

        if ($config['refresh']['enabled']) {
            self::registerRefresh($services, $config);
        }

        $container->parameters()
            ->set(NativeAuthenticatorPass::FIREWALL_PARAMETER, $firewall)
            ->set(NativeAuthenticatorPass::SERVICES_PARAMETER, self::nativeServices($config));
    }

    /**
     * The services of `oidc_login` the bundle reads or decorates for the features turned on.
     *
     * @param Config $config
     *
     * @return list<string>
     */
    public static function nativeServices(array $config): array
    {
        $firewall = $config['firewall'];
        $names = ['', 'id_token.', 'signature_verifier.'];
        if ($config['back_channel_logout']['enabled']) {
            $names[] = 'backchannel_logout.';
            $names[] = 'logout_token.';
            $names[] = 'discovery.';
            $names[] = 'ended_sessions.';
        }

        if ($config['refresh']['enabled']) {
            $names[] = 'token_refresh_listener.';
            $names[] = 'token_refresher.';
        }

        return array_map(static fn (string $name): string => self::NATIVE_AUTHENTICATOR.$name.$firewall, $names);
    }

    /**
     * @param Config $config
     */
    private static function registerBackChannelLogout(ServicesConfigurator $services, array $config): void
    {
        $firewall = $config['firewall'];

        $services->set(CacheReplayStore::class)->args([service($config['back_channel_logout']['cache']), service($config['lock_factory'])]);
        $services->alias(ReplayStore::class, CacheReplayStore::class);

        $services->set('phil_identity_client.back_channel_logout', ReplayGuardedBackChannelLogout::class)
            ->decorate(self::NATIVE_AUTHENTICATOR.'backchannel_logout.'.$firewall)
            ->args([
                service(self::NATIVE_AUTHENTICATOR.'signature_verifier.'.$firewall),
                service(self::NATIVE_AUTHENTICATOR.'logout_token.'.$firewall),
                service(self::NATIVE_AUTHENTICATOR.'discovery.'.$firewall),
                $config['client_id'],
                service(self::NATIVE_AUTHENTICATOR.'ended_sessions.'.$firewall),
                service(ReplayStore::class),
                service('event_dispatcher'),
                service('clock'),
                service('logger'),
                $config['back_channel_logout']['replay_ttl'],
            ]);
    }

    /**
     * @param Config $config
     */
    private static function registerRefresh(ServicesConfigurator $services, array $config): void
    {
        $firewall = $config['firewall'];
        $refresh = $config['refresh'];

        $services->set(EncryptedRefreshOutcomes::class)->args([service($refresh['cache']), $refresh['outcome_ttl']]);
        $services->alias(RefreshOutcomes::class, EncryptedRefreshOutcomes::class);

        $services->set('phil_identity_client.token_refresh_listener', SerializedTokenRefreshListener::class)
            ->decorate(self::NATIVE_AUTHENTICATOR.'token_refresh_listener.'.$firewall)
            ->args([
                service('security.token_storage'),
                service(self::NATIVE_AUTHENTICATOR.'token_refresher.'.$firewall),
                service($config['lock_factory']),
                service(RefreshOutcomes::class),
                service('event_dispatcher'),
                service('clock'),
                service('logger'),
                $refresh['leeway'],
                $refresh['wait_timeout'],
                $refresh['lock_ttl'],
            ]);
    }

    /**
     * @param Config $config
     *
     * @return list<Definition>
     */
    private static function levels(array $config): array
    {
        $levels = [];
        foreach ($config['elevation']['levels'] as $name => $level) {
            $levels[] = new Definition(AssuranceLevel::class, [$name, $level['acr'], $level['max_age']]);
        }

        return $levels;
    }

    /**
     * What the features turned on leave no use for: nothing is kept in the session without a reader.
     *
     * @param Config $config
     *
     * @return list<string>
     */
    private static function discardedAttributes(array $config): array
    {
        if ($config['refresh']['enabled']) {
            return [];
        }

        $discarded = [TokenAttribute::REFRESH_TOKEN];
        if (!$config['tokens']['keep_access_token']) {
            $discarded[] = TokenAttribute::ACCESS_TOKEN;
            $discarded[] = TokenAttribute::ACCESS_TOKEN_EXPIRES_AT;
        }

        if (!$config['tokens']['keep_id_token'] && !$config['end_session']['enabled']) {
            $discarded[] = TokenAttribute::ID_TOKEN;
        }

        return $discarded;
    }
}
