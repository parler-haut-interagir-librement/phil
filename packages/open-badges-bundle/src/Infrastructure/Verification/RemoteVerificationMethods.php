<?php

declare(strict_types=1);

namespace Phil\OpenBadgesBundle\Infrastructure\Verification;

use JsonException;
use Override;
use Phil\OpenBadges\Contract\VerificationKey;
use Phil\OpenBadges\Contract\VerificationMethods;
use Phil\OpenBadges\Did\DidWeb;
use Phil\OpenBadges\Encoding\Base64Url;
use Phil\OpenBadges\Exception\InvalidBadge;
use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\OpenBadges\Key\Ed25519Key;
use Phil\OpenBadges\Key\RsaKey;
use Phil\SafeHttp\Exception\SafeHttpException;
use Phil\SafeHttp\ReadRule;
use Phil\SafeHttp\SafeHttpClient;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * The keys of issuers the application does not configure, read from their profiles: a verification method the keys the
 * application knows do not hold — `https://issuer.example/profile#key-1` or `did:web:issuer.example#key-1` — is looked
 * up in the document of the identifier before its fragment, which is the issuer's: the profile published at that
 * HTTPS address, or the DID document of that `did:web`, read where {@see DidWeb} places it.
 *
 * The document is read by `phil/safe-http`: an HTTPS address of a public host, its redirects checked and its final
 * address still HTTPS, within a time and a number of bytes. It holds when its `id` is the issuer's identifier, and gives the keys of its
 * `verificationMethod` that are fragments of that identifier and controlled by it — an Ed25519 `Multikey` with its
 * `publicKeyMultibase`, or a `JsonWebKey` whose `publicKeyJwk` is an Ed25519 key or a public RSA key
 * {@see RsaKey::fromJwk()} reads —, and, when it lists an `assertionMethod`, only those it lists. The keys found, or the reason the document does not hold,
 * are cached for a bounded time, so that a verifier asks a profile once in a while however many credentials it
 * checks, and an issuer that does not answer is not asked again at once. With a list of trusted issuers, no other
 * document is read.
 *
 * It keeps nothing between requests itself; the cache is the application's pool.
 */
final readonly class RemoteVerificationMethods implements VerificationMethods
{
    /** The most verification methods read from one profile. */
    public const int MAX_METHODS = 20;

    public const string CACHE_PREFIX = 'phil_open_badges.issuer.';

    private const string MEDIA_TYPES = 'application/ld+json, application/json;q=0.9';

    /** The members of a private RSA JSON Web Key: a document that publishes one has lost its key. */
    private const array PRIVATE_MEMBERS = ['d' => true, 'p' => true, 'q' => true, 'dp' => true, 'dq' => true, 'qi' => true, 'oth' => true];

    /**
     * @param list<string> $trustedIssuers the identifiers of the only issuers whose profile is read; any issuer when
     *                                     empty
     * @param int          $ttl            how many seconds the keys of a profile are cached
     * @param int          $failureTtl     how many seconds the refusal of a profile is cached
     */
    public function __construct(
        private VerificationMethods $known,
        private SafeHttpClient $client,
        private CacheInterface $cache,
        private array $trustedIssuers = [],
        private int $ttl = 3600,
        private int $failureTtl = 60,
        private bool $allowLocalhost = false,
    ) {
    }

    #[Override]
    public function key(string $verificationMethod): VerificationKey
    {
        try {
            return $this->known->key($verificationMethod);
        } catch (InvalidCredential $invalidCredential) {
            [$issuer, $address] = $this->issuerOf($verificationMethod) ?? throw $invalidCredential;
        }

        if ([] !== $this->trustedIssuers && !\in_array($issuer, $this->trustedIssuers, true)) {
            throw InvalidCredential::because(\sprintf('The issuer "%s" is not one the verifier trusts.', $issuer));
        }

        /** @var string|array<string, array<string, string>> $profile the reason the profile does not hold, or its keys by identifier */
        $profile = $this->cache->get(self::CACHE_PREFIX.hash('sha256', $issuer), function (ItemInterface $item) use ($issuer, $address): string|array {
            $profile = $this->read($issuer, $address);
            $item->expiresAfter(\is_string($profile) ? $this->failureTtl : $this->ttl);

            return $profile;
        });

        if (\is_string($profile)) {
            throw InvalidCredential::because($profile);
        }

        $key = $profile[$verificationMethod] ?? throw InvalidCredential::because(\sprintf('The profile of the issuer "%s" publishes no key "%s".', $issuer, $verificationMethod));

        return 'RSA' === ($key['kty'] ?? null) ? RsaKey::fromJwk($key) : Ed25519Key::fromJwk($key);
    }

    /**
     * The identifier of the issuer whose document publishes the method, and the address of that document: an HTTPS
     * address without the fragment, read at itself, or a `did:web`, read where {@see DidWeb} places it — never on an IP
     * address nor `localhost`; null when the method is neither.
     *
     * @return array{string, string}|null
     */
    private function issuerOf(string $verificationMethod): ?array
    {
        $issuer = explode('#', $verificationMethod)[0];

        if (DidWeb::isDidWeb($issuer)) {
            try {
                $address = DidWeb::fromString($issuer)->url->toString();
            } catch (InvalidCredential) {
                return null;
            }

            return [$issuer, $address];
        }

        try {
            $url = Url::fromString($issuer, $this->allowLocalhost);
        } catch (InvalidUrl) {
            return null;
        }

        return 'https' === $url->scheme && $issuer === trim($issuer) ? [$issuer, $issuer] : null;
    }

    /**
     * @return string|array<string, array<string, string>> the reason the profile does not hold, or its keys by
     *                                                     identifier
     */
    private function read(string $issuer, string $address): string|array
    {
        try {
            $response = $this->client->get($address, new ReadRule(self::MEDIA_TYPES, ['application/ld+json', 'application/json']));
        } catch (SafeHttpException $safeHttpException) {
            return \sprintf('The profile of the issuer "%s" could not be read: %s', $issuer, $safeHttpException->getMessage());
        }

        if (!$response->isSuccessful()) {
            return \sprintf('The profile of the issuer "%s" could not be read: the answer was %d.', $issuer, $response->status);
        }

        if (!$response->complete) {
            return \sprintf('The profile of the issuer "%s" is not JSON within the bytes the verifier reads.', $issuer);
        }

        if ('https' !== $response->url->scheme) {
            return \sprintf('The profile of the issuer "%s" was redirected away from HTTPS.', $issuer);
        }

        try {
            $profile = json_decode($response->body, true, 32, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $profile = null;
        }

        if (!\is_array($profile)) {
            return \sprintf('The profile of the issuer "%s" is not JSON within the bytes the verifier reads.', $issuer);
        }

        if (($profile['id'] ?? null) !== $issuer) {
            return \sprintf('The profile read at "%s" is not the profile of that issuer: its "id" differs.', $address);
        }

        return $this->keys($issuer, $profile);
    }

    /**
     * @param array<array-key, mixed> $profile
     *
     * @return array<string, array<string, string>> the JSON Web Key of each method that holds, by identifier
     */
    private function keys(string $issuer, array $profile): array
    {
        $methods = \is_array($profile['verificationMethod'] ?? null) ? \array_slice($profile['verificationMethod'], 0, self::MAX_METHODS) : [];
        $asserting = \is_array($profile['assertionMethod'] ?? null) ? array_map(static function (mixed $method) use ($issuer): mixed {
            $id = \is_array($method) ? $method['id'] ?? null : $method;

            return \is_string($id) && str_starts_with($id, '#') ? $issuer.$id : $id;
        }, $profile['assertionMethod']) : null;
        $keys = [];

        foreach ($methods as $method) {
            $id = \is_array($method) ? $method['id'] ?? null : null;
            // A DID document may name its methods by their fragment alone.
            $id = \is_string($id) && str_starts_with($id, '#') ? $issuer.$id : $id;
            $controller = \is_array($method) ? $method['controller'] ?? $issuer : null;

            if (!\is_array($method) || !\is_string($id) || !str_starts_with($id, $issuer.'#') || $controller !== $issuer || (null !== $asserting && !\in_array($id, $asserting, true))) {
                continue;
            }

            $key = $this->jwkOf($method);

            if (null !== $key) {
                $keys[$id] = $key;
            }
        }

        return $keys;
    }

    /**
     * The key of a method as a JSON Web Key reduced to what verifies — an Ed25519 key from its `publicKeyMultibase` or
     * its `publicKeyJwk`, an RSA key from its `publicKeyJwk` —, or null when it holds no such key.
     *
     * @param array<array-key, mixed> $method
     *
     * @return array<string, string>|null
     */
    private function jwkOf(array $method): ?array
    {
        try {
            if (\is_string($method['publicKeyMultibase'] ?? null)) {
                return $this->ed25519(Ed25519Key::fromMultibase($method['publicKeyMultibase']));
            }

            $jwk = $method['publicKeyJwk'] ?? null;

            if (\is_array($jwk)) {
                return 'RSA' === ($jwk['kty'] ?? null) ? $this->rsa($jwk) : $this->ed25519(Ed25519Key::fromJwk($jwk));
            }
        } catch (InvalidBadge|InvalidCredential) {
        }

        return null;
    }

    /**
     * A public RSA key reduced to its type, modulus and exponent, once {@see RsaKey::fromJwk()} has accepted it; null
     * when the key carries a private member.
     *
     * @param array<array-key, mixed> $jwk
     *
     * @return array{kty: 'RSA', n: string, e: string}|null
     *
     * @throws InvalidBadge when the key is no RSA key of 2048 to 8192 bits verifying RS256
     */
    private function rsa(array $jwk): ?array
    {
        if ([] !== array_intersect_key($jwk, self::PRIVATE_MEMBERS)) {
            return null;
        }

        RsaKey::fromJwk($jwk);

        /** @var array{n: string, e: string} $jwk the modulus and the exponent {@see RsaKey::fromJwk()} has just required */
        return ['kty' => 'RSA', 'n' => $jwk['n'], 'e' => $jwk['e']];
    }

    /**
     * @return array<string, string>
     */
    private function ed25519(Ed25519Key $key): array
    {
        return ['kty' => 'OKP', 'crv' => 'Ed25519', 'x' => Base64Url::encode($key->publicKey)];
    }
}
