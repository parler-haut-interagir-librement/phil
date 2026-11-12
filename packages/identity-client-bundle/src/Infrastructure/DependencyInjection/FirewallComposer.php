<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Infrastructure\DependencyInjection;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Writes the `oidc_login` authenticator of the firewall into the configuration of the security bundle.
 *
 * @phpstan-import-type Config from ClientConfiguration
 */
final class FirewallComposer
{
    public const string PROVIDER = 'phil_identity_client';

    public const string USER_PROVIDER_SERVICE = 'phil_identity_client.user_provider';

    /**
     * Prepends the composed block; the configuration of the application is read after it and has the last word.
     *
     * The security bundle refuses a firewall or a user provider a later block introduces, so the first block names
     * them all, in the order the application declares them. A session fixation strategy of `none` is refused: a session
     * identifier set before the sign-in would stay valid after it.
     *
     * @param Config $config
     */
    public static function prepend(ContainerBuilder $container, array $config): void
    {
        $firewalls = [];
        $providers = [];
        foreach ($container->getExtensionConfig('security') as $block) {
            $firewalls += self::names($block, 'firewalls');
            $providers += self::names($block, 'providers');
            if (\is_array($block) && 'none' === ($block['session_fixation_strategy'] ?? null)) {
                throw new InvalidConfigurationException('security.session_fixation_strategy is "none": phil_identity_client needs the session identifier changed at sign-in; use "migrate" or "invalidate".');
            }
        }

        $firewall = $config['firewall'];
        if (!isset($firewalls[$firewall])) {
            throw new InvalidConfigurationException(\sprintf('phil_identity_client.firewall names "%s", which security.firewalls does not declare.', $firewall));
        }

        $firewalls[$firewall] = ['provider' => self::PROVIDER, 'oidc_login' => self::authenticator($config)];

        $container->prependExtensionConfig('security', [
            'providers' => [self::PROVIDER => ['id' => self::USER_PROVIDER_SERVICE]] + $providers,
            'firewalls' => $firewalls,
        ]);
    }

    /**
     * @param Config $config
     *
     * @return array<string, mixed>
     */
    public static function authenticator(array $config): array
    {
        $refresh = $config['refresh'];
        $logout = $config['back_channel_logout'];
        $authenticator = [
            'provider_uri' => $config['issuer'],
            'client_id' => $config['client_id'],
            'client_authentication' => self::clientAuthentication($config),
            'scope' => $config['scopes'],
            'check_path' => $config['paths']['check'],
            'start_path' => $config['paths']['start'],
            'default_target_path' => $config['paths']['landing'],
            'failure_path' => $config['paths']['failure'],
            'user_data_source' => $config['claims_source'],
            'user_identifier_claim' => 'sub',
            'allowed_time_drift' => $config['allowed_time_drift'],
            'id_token_signature' => ['required' => true, 'algorithms' => [$config['signature_algorithm']]],
            'pkce' => ['enabled' => true, 'method' => 'S256'],
            'authorization_params' => $config['authorization_params'],
            'refresh_access_token' => ['enabled' => $refresh['enabled'], 'leeway' => $refresh['leeway']],
            'enable_end_session' => $config['end_session']['enabled'],
            'post_logout_redirect_path' => $config['end_session']['post_logout_path'],
            'backchannel_logout' => $logout['enabled']
                ? ['enabled' => true, 'path' => $logout['path'], 'cache' => $logout['cache'], 'lifetime' => max($logout['replay_ttl'], $config['session']['max_lifetime'])]
                : ['enabled' => false],
        ];

        foreach (['discovery_uri' => $config['discovery_uri'], 'http_client' => $config['http_client'], 'max_age' => $config['elevation']['max_age']] as $option => $value) {
            if (null !== $value) {
                $authenticator[$option] = $value;
            }
        }

        return $authenticator;
    }

    /**
     * @param Config $config
     *
     * @return array<string, mixed>
     */
    private static function clientAuthentication(array $config): array
    {
        if (null !== $config['client_secret']) {
            return ['client_secret_'.$config['client_secret_transport'] => $config['client_secret']];
        }

        if (null !== $config['client_key']) {
            return ['private_key_jwt' => ['key' => $config['client_key'], 'algorithm' => $config['client_key_algorithm']]];
        }

        return ['none' => true];
    }

    /**
     * @return array<array-key, array{}>
     */
    private static function names(mixed $block, string $section): array
    {
        $entries = \is_array($block) ? ($block[$section] ?? null) : null;

        return \is_array($entries) ? array_fill_keys(array_keys($entries), []) : [];
    }
}
