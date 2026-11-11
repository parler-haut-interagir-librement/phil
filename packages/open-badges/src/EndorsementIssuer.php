<?php

declare(strict_types=1);

namespace Phil\OpenBadges;

use DateTimeImmutable;
use Phil\OpenBadges\Exception\InvalidBadge;

/**
 * Builds the `EndorsementCredential` a third party signs to support an achievement, an issuer's profile or a
 * credential: the endorser is its issuer, the endorsed thing its subject, with the endorser's comment.
 *
 * The endorser secures it with its own key, as a JSON Web Token or a Data Integrity proof, and the issuer of the
 * endorsed thing carries it in an `Achievement`, an `Issuer` or a credential.
 */
final readonly class EndorsementIssuer
{
    public function __construct(
        private Issuer $endorser,
    ) {
    }

    /**
     * The endorsement, unsecured.
     *
     * @return array<string, mixed>
     */
    public function credential(string $endorsed, string $name, string $comment, string $id, DateTimeImmutable $validFrom, ?DateTimeImmutable $validUntil = null): array
    {
        Credentials::checkId($id);

        if (1 !== preg_match('#^(urn:uuid:\S+|https?://\S+|did:[a-z0-9]+:\S+)$#D', $endorsed)) {
            throw InvalidBadge::because(\sprintf('What an endorsement supports is identified by a URL, a "urn:uuid:" or a DID; "%s" is not.', $endorsed));
        }

        if ('' === trim($name)) {
            throw InvalidBadge::because('An endorsement has a name.');
        }

        return [
            '@context' => Context::CREDENTIAL,
            'id' => $id,
            'type' => ['VerifiableCredential', 'EndorsementCredential'],
            'name' => $name,
            'issuer' => $this->endorser->toArray(),
            ...Credentials::period($validFrom, $validUntil),
            'credentialSubject' => array_filter([
                'id' => $endorsed,
                'type' => ['EndorsementSubject'],
                'endorsementComment' => '' === trim($comment) ? null : $comment,
            ], static fn (array|string|null $value): bool => null !== $value),
        ];
    }
}
