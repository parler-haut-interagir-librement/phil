<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Jose;

use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use JsonException;
use OpenSSLAsymmetricKey;
use Override;
use Phil\IdentityProviderBundle\Application\Contract\Signer;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Encoding\Der;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use SensitiveParameter;
use Throwable;

/**
 * A key the provider signs its tokens with: an RSA key of 3072 bits, a P-256 key or an Ed25519 key, with its key
 * identifier, held as a JSON Web Key of `web-token/jwt-library`. Its material — the private JSON Web Key — is what the
 * key store keeps, encrypted when a key management service is configured; a PEM, or a libsodium Ed25519 secret key in
 * base64url, is read too.
 *
 * The key is loaded once, when the object is made: an OpenSSL key for RS256 and ES256, the libsodium secret key for
 * EdDSA. Each signature then costs the signature only, not the conversion of the JSON Web Key; keep the object to keep
 * that saving, as the key sources do.
 */
final readonly class PrivateKey implements Signer
{
    public const int RSA_BITS = 3072;

    public const int MIN_RSA_BITS = 2048;

    /** @var OpenSSLAsymmetricKey|non-empty-string */
    private OpenSSLAsymmetricKey|string $signing;

    private function __construct(
        public SigningAlgorithm $algorithm,
        public string $kid,
        #[SensitiveParameter]
        private JWK $jwk,
    ) {
        $this->signing = $this->load($algorithm, $jwk);
    }

    public static function generate(SigningAlgorithm $algorithm, ?string $kid = null): self
    {
        $values = ['kid' => $kid ?? Base64Url::encode(random_bytes(12)), 'alg' => $algorithm->value, 'use' => 'sig'];

        return self::of($algorithm, match ($algorithm) {
            SigningAlgorithm::RS256 => JWKFactory::createRSAKey(self::RSA_BITS, $values),
            SigningAlgorithm::ES256 => JWKFactory::createECKey('P-256', $values),
            SigningAlgorithm::EdDSA => JWKFactory::createOKPKey('Ed25519', $values),
        });
    }

    /**
     * A key read back from the store.
     *
     * @throws InvalidValue when the material is not a private key of the algorithm
     */
    public static function fromMaterial(SigningAlgorithm $algorithm, string $kid, #[SensitiveParameter] string $material): self
    {
        $values = ['kid' => $kid, 'alg' => $algorithm->value, 'use' => 'sig'];

        try {
            $jwk = match (true) {
                str_starts_with($material, '{') => new JWK($values + self::object($material)),
                str_starts_with($material, '-----BEGIN ') => JWKFactory::createFromKey($material, null, $values),
                default => new JWK($values + self::ed25519Secret($material)),
            };
        } catch (Throwable) {
            throw InvalidValue::because('The material is not a private key the provider reads.');
        }

        return self::of($algorithm, $jwk);
    }

    /**
     * The private JSON Web Key, as the key store keeps it.
     */
    public function material(): string
    {
        return json_encode($this->jwk, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
    }

    /**
     * The JSON Web Key that signs, for the library.
     */
    public function jwk(): JWK
    {
        return $this->jwk;
    }

    #[Override]
    public function sign(string $input): string
    {
        if (\is_string($this->signing)) {
            return sodium_crypto_sign_detached($input, $this->signing);
        }

        if (!openssl_sign($input, $signature, $this->signing, \OPENSSL_ALGO_SHA256) || !\is_string($signature)) {
            throw InvalidValue::because('OpenSSL could not sign with the key.');
        }

        return SigningAlgorithm::ES256 === $this->algorithm ? Der::ecdsaSignature($signature, 32) : $signature;
    }

    /**
     * The JWK thumbprint of the public key (RFC 7638), with SHA-256 in base64url: an identifier the key itself gives.
     */
    public function thumbprint(): string
    {
        return $this->jwk->thumbprint('sha256');
    }

    #[Override]
    public function publicJwk(): array
    {
        /** @var array<string, string> $public */
        $public = array_filter($this->jwk->toPublic()->all(), is_string(...));

        return array_merge(['kty' => $this->algorithm->keyType(), 'use' => 'sig', 'alg' => $this->algorithm->value, 'kid' => $this->kid], $public);
    }

    private static function of(SigningAlgorithm $algorithm, JWK $jwk): self
    {
        $all = $jwk->all();
        $kid = $all['kid'] ?? null;

        if (!\is_string($kid) || '' === $kid) {
            throw InvalidValue::because('A signing key has an identifier.');
        }

        [$type, $curve, $members] = match ($algorithm) {
            SigningAlgorithm::RS256 => ['RSA', null, ['n', 'e', 'd', 'p', 'q']],
            SigningAlgorithm::ES256 => ['EC', 'P-256', ['x', 'y', 'd']],
            SigningAlgorithm::EdDSA => ['OKP', 'Ed25519', ['x', 'd']],
        };
        $missing = array_filter($members, static fn (string $member): bool => !\is_string($all[$member] ?? null) || '' === $all[$member]);

        if ($type !== ($all['kty'] ?? null) || $curve !== ($all['crv'] ?? null) || [] !== $missing) {
            throw InvalidValue::because(\sprintf('The material is not a private key of %s.', $algorithm->value));
        }

        /** @var string $modulus checked with the other members */
        $modulus = $all['n'] ?? '';

        if (SigningAlgorithm::RS256 === $algorithm && 8 * \strlen(ltrim(Base64Url::decode($modulus), "\x00")) < self::MIN_RSA_BITS) {
            throw InvalidValue::because(\sprintf('An RSA signing key has a modulus of at least %d bits.', self::MIN_RSA_BITS));
        }

        return new self($algorithm, $kid, $jwk);
    }

    /**
     * The key in the form that signs: an OpenSSL key built from the members of the JSON Web Key, or the libsodium
     * secret key of an Ed25519 seed.
     *
     * @return OpenSSLAsymmetricKey|non-empty-string
     */
    private function load(SigningAlgorithm $algorithm, #[SensitiveParameter] JWK $jwk): OpenSSLAsymmetricKey|string
    {
        $member = static function (string $name) use ($jwk): string {
            $value = $jwk->has($name) ? $jwk->get($name) : null;

            return \is_string($value) ? Base64Url::decode($value) : '';
        };

        if (SigningAlgorithm::EdDSA === $algorithm) {
            $seed = $member('d');

            if (\SODIUM_CRYPTO_SIGN_SEEDBYTES !== \strlen($seed)) {
                throw InvalidValue::because('An Ed25519 private key is a seed of 32 bytes.');
            }

            $pair = sodium_crypto_sign_seed_keypair($seed);

            if (!hash_equals(sodium_crypto_sign_publickey($pair), $member('x'))) {
                throw InvalidValue::because('The public key of the Ed25519 key is not the one of its seed.');
            }

            return sodium_crypto_sign_secretkey($pair);
        }

        $details = SigningAlgorithm::RS256 === $algorithm
            ? ['rsa' => array_filter(['n' => $member('n'), 'e' => $member('e'), 'd' => $member('d'), 'p' => $member('p'), 'q' => $member('q'), 'dmp1' => $member('dp'), 'dmq1' => $member('dq'), 'iqmp' => $member('qi')], static fn (string $value): bool => '' !== $value)]
            : ['ec' => ['curve_name' => 'prime256v1', 'x' => $member('x'), 'y' => $member('y'), 'd' => $member('d')]];
        $key = openssl_pkey_new($details);

        return $key instanceof OpenSSLAsymmetricKey ? $key : throw InvalidValue::because(\sprintf('OpenSSL does not read the key of %s.', $algorithm->value));
    }

    /**
     * @return array<string, mixed>
     */
    private static function object(string $json): array
    {
        try {
            /** @var array<string, mixed> $value the material starts with `{`: an object, which a JSON Web Key needs members of */
            $value = json_decode($json, true, 4, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidValue::because('The material is not JSON.');
        }

        return $value;
    }

    /**
     * The JSON Web Key members of a libsodium Ed25519 secret key: its seed and its public key.
     *
     * @return array{kty: 'OKP', crv: 'Ed25519', d: string, x: string}
     */
    private static function ed25519Secret(string $material): array
    {
        $secret = Base64Url::decode($material);

        if (\SODIUM_CRYPTO_SIGN_SECRETKEYBYTES !== \strlen($secret)) {
            throw InvalidValue::because('An Ed25519 secret key holds 64 bytes.');
        }

        return ['kty' => 'OKP', 'crv' => 'Ed25519', 'd' => Base64Url::encode(substr($secret, 0, 32)), 'x' => Base64Url::encode(substr($secret, 32))];
    }
}
