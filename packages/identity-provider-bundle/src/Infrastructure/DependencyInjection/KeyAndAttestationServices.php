<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\DependencyInjection;

use Phil\IdentityProviderBundle\Application\Contract\SecretCipher;
use Phil\IdentityProviderBundle\Application\Contract\SigningKeySource;
use Phil\IdentityProviderBundle\Application\Oidc\FileSigningKeys;
use Phil\IdentityProviderBundle\Application\Oidc\SigningKeys;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use Phil\IdentityProviderBundle\Domain\WebAuthn\AttestationPolicy;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The services of two options that read files or name a source: the attestation policy of new passkeys, its trusted
 * roots read when the container is built, and the source of the signing keys.
 */
final class KeyAndAttestationServices
{
    private function __construct()
    {
    }

    /**
     * The policy of the attestations of new passkeys, its trusted roots read from their files now.
     *
     * @param array{conveyance: string, trusted_roots: list<string>, require_trusted: bool, allowed_aaguids: list<string>, denied_aaguids: list<string>, backup: string} $attestation
     *
     * @throws InvalidConfigurationException when a root cannot be read or the options contradict each other
     */
    public static function attestationPolicy(array $attestation): Definition
    {
        $roots = [];

        foreach ($attestation['trusted_roots'] as $path) {
            $pem = is_file($path) && is_readable($path) ? file_get_contents($path) : false;

            if (!\is_string($pem) || false === @openssl_x509_read($pem)) {
                throw new InvalidConfigurationException(\sprintf('The trusted root "%s" of "passkeys.attestation" is not a readable PEM certificate.', $path));
            }

            $roots[] = $pem;
        }

        $arguments = [$attestation['conveyance'], $roots, $attestation['require_trusted'], $attestation['allowed_aaguids'], $attestation['denied_aaguids'], $attestation['backup']];

        try {
            new AttestationPolicy(...$arguments);
        } catch (InvalidValue $invalidValue) {
            throw new InvalidConfigurationException(\sprintf('"passkeys.attestation": %s', $invalidValue->getMessage()), 0, $invalidValue);
        }

        return new Definition(AttestationPolicy::class, $arguments);
    }

    /**
     * The source of the signing keys: the database, key files, or a service of the application.
     *
     * @param array{source: string, automatic_rotation: bool, files: list<array{path: string, kid: ?string}>, sealed: bool, service: ?string} $keys
     *
     * @throws InvalidConfigurationException when the source lacks what it reads
     */
    public static function signingKeySource(ContainerBuilder $container, array $keys, string $algorithm, bool $kms): void
    {
        if ($keys['sealed'] && (IdentityProviderServices::KEYS_FILE !== $keys['source'] || !$kms)) {
            throw new InvalidConfigurationException('"oidc.signing_keys.sealed" is for the source "file", and needs "secrets.kms_key".');
        }

        if (IdentityProviderServices::KEYS_FILE === $keys['source']) {
            if ([] === $keys['files']) {
                throw new InvalidConfigurationException('The source "file" of "oidc.signing_keys" needs "files", the key that signs first.');
            }

            $container->register(FileSigningKeys::class, FileSigningKeys::class)
                ->setArguments([$keys['files'], new Definition(SigningAlgorithm::class, [$algorithm])->setFactory([SigningAlgorithm::class, 'from']), $keys['sealed'] ? new Reference(SecretCipher::class) : null]);
            $container->setAlias(SigningKeySource::class, FileSigningKeys::class)->setPublic(true);

            return;
        }

        if (IdentityProviderServices::KEYS_SERVICE === $keys['source']) {
            if (null === $keys['service']) {
                throw new InvalidConfigurationException('The source "service" of "oidc.signing_keys" needs "service", the id of a service implementing SigningKeySource.');
            }

            $container->setAlias(SigningKeySource::class, $keys['service'])->setPublic(true);

            return;
        }

        $container->setAlias(SigningKeySource::class, SigningKeys::class)->setPublic(true);
    }
}
