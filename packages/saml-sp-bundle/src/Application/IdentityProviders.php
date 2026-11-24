<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Application;

use Phil\SamlSp\Exception\InvalidMetadata;
use Phil\SamlSp\Key\Certificate;
use Phil\SamlSp\Key\KeyPair;
use Phil\SamlSp\Key\PrivateKey;
use Phil\SamlSp\Metadata\MetadataLoader;
use Phil\SamlSp\Provider\IdentityProvider;
use Phil\SamlSp\Provider\ServiceProvider;
use Phil\SamlSpBundle\Application\Contract\EndpointUrls;
use Phil\Url\Url;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The identity providers of the application, one per organisation, and the service provider each one sees: the
 * metadata loaded through the cache of `MetadataLoader`, the key pairs read once per request, both forgotten by
 * `reset()` between the requests of a worker.
 */
final class IdentityProviders implements ResetInterface
{
    /** @var array<string, IdentityProviderSettings> */
    private readonly array $settings;

    /** @var array<string, IdentityProvider> */
    private array $loaded = [];

    /** @var array{0?: KeyPair|null, 1?: list<KeyPair>} */
    private array $keys = [];

    /**
     * @param list<IdentityProviderSettings> $settings
     */
    public function __construct(
        array $settings,
        private readonly ServiceProviderSettings $serviceProvider,
        private readonly MetadataLoader $loader,
        private readonly EndpointUrls $endpoints,
    ) {
        $byKey = [];

        foreach ($settings as $setting) {
            $byKey[$setting->key] = $setting;
        }

        $this->settings = $byKey;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->settings);
    }

    public function has(string $key): bool
    {
        return isset($this->settings[$key]);
    }

    public function settings(string $key): IdentityProviderSettings
    {
        return $this->settings[$key] ?? throw InvalidMetadata::because(\sprintf('No identity provider is configured under "%s".', $key));
    }

    public function identityProvider(string $key, bool $refresh = false): IdentityProvider
    {
        if (!$refresh && isset($this->loaded[$key])) {
            return $this->loaded[$key];
        }

        $settings = $this->settings($key);
        $trusted = array_map(Certificate::fromPem(...), $settings->metadataCertificates);
        $metadata = null === $settings->metadataUrl
            ? $this->loader->fromXml($settings->metadataXml ?? $this->read((string) $settings->metadataFile), $settings->entityId, $trusted)
            : $this->loader->fromUrl(Url::fromString($settings->metadataUrl), $settings->entityId, $trusted, $refresh);

        return $this->loaded[$key] = new IdentityProvider(
            $metadata,
            $settings->attributes,
            $settings->wantAssertionsSigned,
            $settings->wantAssertionsEncrypted,
            $settings->allowUnsolicited,
            $settings->binding,
        );
    }

    public function serviceProvider(string $key): ServiceProvider
    {
        $this->settings($key);

        return new ServiceProvider(
            $this->serviceProvider->entityId ?? $this->endpoints->metadata($key)->toString(),
            $this->endpoints->assertionConsumerService($key),
            $this->settings[$key]->singleLogout ? $this->endpoints->singleLogoutService($key) : null,
            $this->signing(),
            $this->encryption(),
            $this->serviceProvider->nameIdFormat,
            $this->serviceProvider->signRequests,
        );
    }

    public function signing(): ?KeyPair
    {
        if (!\array_key_exists(0, $this->keys)) {
            $this->keys[0] = null === $this->serviceProvider->signing ? null : $this->pair($this->serviceProvider->signing);
        }

        return $this->keys[0];
    }

    /**
     * @return list<KeyPair>
     */
    public function encryption(): array
    {
        return $this->keys[1] ??= array_map($this->pair(...), $this->serviceProvider->encryption);
    }

    public function reset(): void
    {
        $this->loaded = [];
        $this->keys = [];
    }

    private function read(string $file): string
    {
        $xml = is_file($file) ? file_get_contents($file) : false;

        return false === $xml ? throw InvalidMetadata::because(\sprintf('The metadata file "%s" cannot be read.', $file)) : $xml;
    }

    /**
     * @param array{certificate: string, private_key: string, passphrase: string|null} $pem
     */
    private function pair(array $pem): KeyPair
    {
        return new KeyPair(PrivateKey::fromPem($pem['private_key'], $pem['passphrase']), Certificate::fromPem($pem['certificate']));
    }
}
