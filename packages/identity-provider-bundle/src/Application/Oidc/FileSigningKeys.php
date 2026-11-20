<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Oidc;

use Jose\Component\Core\JWK;
use Override;
use Phil\IdentityProviderBundle\Application\Contract\SecretCipher;
use Phil\IdentityProviderBundle\Application\Contract\SigningKeySource;
use Phil\IdentityProviderBundle\Application\Jose\PrivateKey;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;

/**
 * Signing keys read from files — a PEM, a private JSON Web Key, or either sealed by the key management service with
 * `phil:identity-provider:seal-key` —, the first one signing and the others published only, for the tokens they signed
 * before. The bundle never rotates them: a rotation is a deployment that puts a new file first and keeps the previous
 * one after it while its tokens may still be presented.
 *
 * A key's identifier is the one configured, or else the JWK thumbprint of its public key (RFC 7638). The files are
 * read and their keys loaded once per process, at the first token signed or read.
 */
final class FileSigningKeys implements SigningKeySource
{
    /** The additional data a key file is sealed under. */
    public const string SEAL_CONTEXT = 'signing-key-file';

    public const int MAX_FILE_SIZE = 65_536;

    /** @var non-empty-list<PrivateKey>|null */
    private ?array $keys = null;

    /**
     * @param list<array{path: string, kid?: ?string}> $files  the key that signs first; at least one
     * @param ?SecretCipher                            $cipher opens every file, all sealed; null when they are
     *                                                         in clear
     */
    public function __construct(
        private readonly array $files,
        private readonly SigningAlgorithm $algorithm,
        private readonly ?SecretCipher $cipher = null,
    ) {
    }

    #[Override]
    public function active(): PrivateKey
    {
        return $this->keys()[0];
    }

    #[Override]
    public function jwks(): array
    {
        return ['keys' => array_map(static fn (PrivateKey $key): array => $key->publicJwk(), $this->keys())];
    }

    #[Override]
    public function publicKey(string $kid): ?JWK
    {
        $key = array_find($this->keys(), static fn (PrivateKey $key): bool => $key->kid === $kid);

        return null === $key ? null : new JWK($key->publicJwk());
    }

    /**
     * @return non-empty-list<PrivateKey>
     *
     * @throws InvalidValue when a file cannot be read, is not a private key of the algorithm, or two keys share an
     *                      identifier
     */
    private function keys(): array
    {
        if (null !== $this->keys) {
            return $this->keys;
        }

        $keys = [];

        foreach ($this->files as $file) {
            $key = $this->load($file['path'], $file['kid'] ?? null);

            if (isset($keys[$key->kid])) {
                throw InvalidValue::because(\sprintf('Two signing key files have the identifier "%s".', $key->kid));
            }

            $keys[$key->kid] = $key;
        }

        $keys = array_values($keys);

        if ([] === $keys) {
            throw InvalidValue::because('The file source of signing keys names at least one file.');
        }

        return $this->keys = $keys;
    }

    private function load(string $path, ?string $kid): PrivateKey
    {
        $material = is_file($path) && is_readable($path) && filesize($path) <= self::MAX_FILE_SIZE ? file_get_contents($path) : false;

        if (!\is_string($material) || '' === trim($material)) {
            throw InvalidValue::because(\sprintf('The signing key file "%s" cannot be read, or is empty or larger than 64 KiB.', $path));
        }

        $material = trim($material);

        if ($this->cipher instanceof SecretCipher) {
            $material = $this->cipher->open($material, self::SEAL_CONTEXT);
        }

        $key = PrivateKey::fromMaterial($this->algorithm, $kid ?? 'file', $material);

        return null === $kid ? PrivateKey::fromMaterial($this->algorithm, $key->thumbprint(), $material) : $key;
    }
}
