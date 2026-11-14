<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Did;

use JsonException;
use Override;
use Phil\OpenBadges\Contract\DocumentFetcher;
use Phil\OpenBadges\Contract\VerificationKey;
use Phil\OpenBadges\Contract\VerificationMethods;
use Phil\OpenBadges\DataIntegrity\DidKeyMethods;
use Phil\OpenBadges\Exception\InvalidBadge;
use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\OpenBadges\Key\Ed25519Key;
use Phil\OpenBadges\Key\RsaKey;

/**
 * The verification methods of `did:web` issuers, read from their DID documents, and every other method from the methods
 * given — the keys the application knows and every Ed25519 `did:key` by default.
 *
 * A method `did:web:example.com#key-1` is accepted only when the document at the address of `did:web:example.com`:
 *
 * - is a JSON object of at most `MAX_BYTES` bytes whose `id` is that identifier;
 * - lists the method in `verificationMethod`, or embeds it in `assertionMethod`, its `id` written whole or as a
 *   fragment, its `controller` the identifier;
 * - names it in `assertionMethod`, the relationship by which an issuer signs its credentials;
 * - gives its key as a `publicKeyMultibase` — an Ed25519 Multikey — or a public `publicKeyJwk`: Ed25519 (`OKP`) or RSA
 *   of 2048 to 8192 bits.
 *
 * Each call reads the document again: nothing is kept between two verifications, so the service is safe in a
 * long-running worker. An application that verifies often decorates its `DocumentFetcher` with a cache.
 */
final readonly class DidWebMethods implements VerificationMethods
{
    /**
     * The most bytes of a DID document.
     */
    public const int MAX_BYTES = 262_144;

    /**
     * The deepest nesting of a DID document read.
     */
    private const int MAX_DEPTH = 16;

    public function __construct(
        private DocumentFetcher $fetcher,
        private VerificationMethods $others = new DidKeyMethods(),
    ) {
    }

    #[Override]
    public function key(string $verificationMethod): VerificationKey
    {
        if (!DidWeb::isDidWeb($verificationMethod)) {
            return $this->others->key($verificationMethod);
        }

        [$did, $fragment] = explode('#', $verificationMethod, 2) + [1 => ''];

        if ('' === $fragment) {
            throw InvalidCredential::because(\sprintf('The verification method "%s" names no key of its DID document.', $verificationMethod));
        }

        $didWeb = DidWeb::fromString($did);
        $document = $this->document($didWeb);
        $method = $this->method($document, $did, $verificationMethod) ?? throw InvalidCredential::because(\sprintf('The DID document of "%s" lists no verification method "%s" for its assertions.', $did, $verificationMethod));

        if ($did !== ($method['controller'] ?? null)) {
            throw InvalidCredential::because(\sprintf('The verification method "%s" is not controlled by "%s".', $verificationMethod, $did));
        }

        try {
            return $this->keyOf($method);
        } catch (InvalidBadge $invalidBadge) {
            throw InvalidCredential::because(\sprintf('The key of the verification method "%s" is refused: %s', $verificationMethod, $invalidBadge->getMessage()));
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private function document(DidWeb $didWeb): array
    {
        $body = $this->fetcher->fetch($didWeb->url);

        if (\strlen($body) > self::MAX_BYTES) {
            throw InvalidCredential::because(\sprintf('A DID document is %d bytes at most.', self::MAX_BYTES));
        }

        try {
            $document = json_decode($body, true, self::MAX_DEPTH, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidCredential::because(\sprintf('The DID document of "%s" is not JSON of %d levels at most.', $didWeb->did, self::MAX_DEPTH));
        }

        if (!\is_array($document) || array_is_list($document) || $didWeb->did !== ($document['id'] ?? null)) {
            throw InvalidCredential::because(\sprintf('The document served for "%s" is not its DID document.', $didWeb->did));
        }

        return $document;
    }

    /**
     * The method named, listed or embedded, and named in `assertionMethod`; null when it is not.
     *
     * @param array<array-key, mixed> $document
     *
     * @return array<array-key, mixed>|null
     */
    private function method(array $document, string $did, string $verificationMethod): ?array
    {
        $asserted = false;
        $found = null;

        foreach ($this->entries($document['assertionMethod'] ?? null) as $entry) {
            if (\is_string($entry)) {
                $asserted = $asserted || $this->absolute($entry, $did) === $verificationMethod;
            } elseif (\is_array($entry) && \is_string($entry['id'] ?? null) && $this->absolute($entry['id'], $did) === $verificationMethod) {
                $asserted = true;
                $found ??= $entry;
            }
        }

        if (!$asserted) {
            return null;
        }

        foreach ($this->entries($document['verificationMethod'] ?? null) as $entry) {
            if (\is_array($entry) && \is_string($entry['id'] ?? null) && $this->absolute($entry['id'], $did) === $verificationMethod) {
                return $found ?? $entry;
            }
        }

        return $found;
    }

    /**
     * @return list<mixed>
     */
    private function entries(mixed $value): array
    {
        return \is_array($value) && array_is_list($value) ? $value : [];
    }

    /**
     * An identifier of a method as written in the document: whole, or a fragment of the document's identifier.
     */
    private function absolute(string $id, string $did): string
    {
        return str_starts_with($id, '#') ? $did.$id : $id;
    }

    /**
     * @param array<array-key, mixed> $method
     *
     * @throws InvalidBadge
     * @throws InvalidCredential
     */
    private function keyOf(array $method): VerificationKey
    {
        $multibase = $method['publicKeyMultibase'] ?? null;
        $jwk = $method['publicKeyJwk'] ?? null;

        if (\is_string($multibase) && null === $jwk) {
            return Ed25519Key::fromMultibase($multibase);
        }

        if (!\is_array($jwk) || null !== $multibase) {
            throw InvalidCredential::because('A verification method gives its key as one "publicKeyMultibase" or one "publicKeyJwk".');
        }

        if (isset($jwk['d']) || isset($jwk['p']) || isset($jwk['q'])) {
            throw InvalidCredential::because('A DID document publishes public keys only; this one publishes a private key.');
        }

        return match ($jwk['kty'] ?? null) {
            'OKP' => Ed25519Key::fromJwk($jwk),
            'RSA' => RsaKey::fromJwk($jwk),
            default => throw InvalidCredential::because('A verification method holds an Ed25519 or an RSA JSON Web Key.'),
        };
    }
}
