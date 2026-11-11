<?php

declare(strict_types=1);

namespace Phil\OpenBadges;

use Phil\OpenBadges\Exception\InvalidBadge;
use Phil\OpenBadges\Key\Ed25519Key;

/**
 * Who issues the credentials: a `Profile` in Open Badges 3.0, its identifier an absolute URL — where its profile and
 * its public keys are published —, its name, its web site, contact address and image if it gives them, and the
 * endorsements third parties signed for it.
 */
final readonly class Issuer
{
    /**
     * @param list<array<string, mixed>|string> $endorsements signed `EndorsementCredential`s, documents or tokens
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $url = null,
        public ?string $email = null,
        public ?string $image = null,
        public array $endorsements = [],
    ) {
        Achievement::checkUrl($id, 'issuer');

        if (null !== $url) {
            Achievement::checkUrl($url, "issuer's web site");
        }

        if (null !== $image) {
            Achievement::checkUrl($image, "issuer's image");
        }

        if (null !== $email && 1 !== preg_match('/^[^\s@<>"]+@[^\s@<>"]+$/D', $email)) {
            throw InvalidBadge::because(\sprintf('"%s" is not an e-mail address.', $email));
        }

        if ('' === trim($name)) {
            throw InvalidBadge::because('An issuer has a name.');
        }

        Endorsements::fields($endorsements);
    }

    /**
     * The issuer as an Open Badges `Profile`, embedded in the credentials it issues.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $profile = array_filter([
            'id' => $this->id,
            'type' => ['Profile'],
            'name' => $this->name,
            'url' => $this->url,
            'email' => $this->email,
            'image' => null === $this->image ? null : ['id' => $this->image, 'type' => 'Image'],
        ], static fn (array|string|null $value): bool => null !== $value);

        return [...$profile, ...Endorsements::fields($this->endorsements)];
    }

    /**
     * The profile document published at the issuer's identifier: the `Profile` with its contexts and, for Data
     * Integrity proofs, its Ed25519 keys as Multikey verification methods it asserts with.
     *
     * @param array<string, Ed25519Key> $verificationMethods the keys by method identifier, `<issuer id>#key-1`
     *
     * @return array<string, mixed>
     */
    public function profileDocument(array $verificationMethods = []): array
    {
        $document = ['@context' => [...Context::CREDENTIAL, Context::MULTIKEY], ...$this->toArray()];

        if ([] === $verificationMethods) {
            return $document;
        }

        $methods = [];

        foreach ($verificationMethods as $id => $key) {
            if (!str_starts_with($id, $this->id.'#')) {
                throw InvalidBadge::because(\sprintf('A verification method of the issuer is a fragment of its identifier; "%s" is not.', $id));
            }

            $methods[] = ['id' => $id, 'type' => 'Multikey', 'controller' => $this->id, 'publicKeyMultibase' => $key->toMultibase()];
        }

        return [...$document, 'verificationMethod' => $methods, 'assertionMethod' => array_keys($verificationMethods)];
    }
}
