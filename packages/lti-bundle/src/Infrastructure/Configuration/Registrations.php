<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Configuration;

use Phil\Lti\Key\InMemoryKeyStore;
use Phil\Lti\Key\RsaPrivateKey;
use Phil\Lti\Key\RsaPublicKey;
use Phil\Lti\Registration\InMemoryPlatformRegistrations;
use Phil\Lti\Registration\InMemoryToolRegistrations;
use Phil\Lti\Registration\PlatformRegistration;
use Phil\Lti\Registration\ToolRegistration;
use Phil\Url\Url;
use SensitiveParameter;

/**
 * Builds the registrations and the keys of the configuration, when the container makes the services.
 *
 * @phpstan-type PlatformConfig array{issuer: string, client_id: string, authorization_url: string, token_url: string, key_set_url: string, token_audience: ?string, deployments: list<string>}
 * @phpstan-type ToolConfig array{client_id: string, name: ?string, login_url: string, redirect_urls: list<string>, key_set_url: string, deep_linking_url: ?string, scopes: list<string>, deployments: list<string>}
 */
final class Registrations
{
    /**
     * @param list<PlatformConfig> $platforms
     */
    public static function platforms(array $platforms, bool $allowLocalhost): InMemoryPlatformRegistrations
    {
        $registrations = new InMemoryPlatformRegistrations();

        foreach ($platforms as $platform) {
            $registrations->add(new PlatformRegistration(
                $platform['issuer'],
                $platform['client_id'],
                Url::fromString($platform['authorization_url'], $allowLocalhost),
                Url::fromString($platform['token_url'], $allowLocalhost),
                Url::fromString($platform['key_set_url'], $allowLocalhost),
                $platform['token_audience'],
            ), $platform['deployments']);
        }

        return $registrations;
    }

    /**
     * @param list<ToolConfig> $tools
     */
    public static function tools(array $tools, bool $allowLocalhost): InMemoryToolRegistrations
    {
        $registrations = new InMemoryToolRegistrations();

        foreach ($tools as $tool) {
            $registrations->add(new ToolRegistration(
                $tool['client_id'],
                Url::fromString($tool['login_url'], $allowLocalhost),
                array_map(static fn (string $url): Url => Url::fromString($url, $allowLocalhost), $tool['redirect_urls']),
                Url::fromString($tool['key_set_url'], $allowLocalhost),
                $tool['scopes'],
                null === $tool['deep_linking_url'] ? null : Url::fromString($tool['deep_linking_url'], $allowLocalhost),
                $tool['name'],
            ), $tool['deployments']);
        }

        return $registrations;
    }

    /**
     * The keys of the configuration: the private key that signs, in PEM, and the public keys of earlier rotations.
     *
     * @param list<string> $retired
     */
    public static function keys(#[SensitiveParameter] string $privateKey, ?string $keyId, array $retired, #[SensitiveParameter] ?string $passphrase = null): InMemoryKeyStore
    {
        return new InMemoryKeyStore(
            RsaPrivateKey::fromPem($privateKey, $keyId, $passphrase),
            array_map(static fn (string $pem): RsaPublicKey => RsaPublicKey::fromPem($pem, RsaPublicKey::fromPem($pem)->thumbprint()), $retired),
        );
    }
}
