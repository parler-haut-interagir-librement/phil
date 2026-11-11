<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Clr;

use DateTimeImmutable;
use Phil\OpenBadges\Context;
use Phil\OpenBadges\Credentials;
use Phil\OpenBadges\Exception\InvalidBadge;
use Phil\OpenBadges\Issuer;
use Phil\OpenBadges\Recipient;

/**
 * Builds a Comprehensive Learner Record 2.0: a `ClrCredential` that gathers a learner's credentials in one record the
 * issuer signs. A credential secured with a Data Integrity proof is embedded as it is; one secured as a JSON Web Token
 * is embedded as an `EnvelopedVerifiableCredential`, its token in a `data:` URL.
 *
 * The record is secured like any credential: as a JSON Web Token, or with a Data Integrity proof.
 */
final readonly class ClrIssuer
{
    public const int MAX_CREDENTIALS = 200;

    public function __construct(
        private Issuer $issuer,
    ) {
    }

    /**
     * The learner record, unsecured; `$partial` when it holds only some of the learner's credentials.
     *
     * @param list<array<string, mixed>|string> $credentials secured credentials: documents with their proof, or tokens
     *
     * @return array<string, mixed>
     */
    public function credential(Recipient $learner, array $credentials, string $name, string $id, DateTimeImmutable $validFrom, ?DateTimeImmutable $validUntil = null, bool $partial = false): array
    {
        Credentials::checkId($id);

        if ([] === $credentials || \count($credentials) > self::MAX_CREDENTIALS) {
            throw InvalidBadge::because(\sprintf('A learner record holds from 1 to %d credentials.', self::MAX_CREDENTIALS));
        }

        if ('' === trim($name)) {
            throw InvalidBadge::because('A learner record has a name.');
        }

        $record = [
            '@context' => [Context::CREDENTIALS_V2, Context::CLR, Context::OPEN_BADGES],
            'id' => $id,
            'type' => ['VerifiableCredential', 'ClrCredential'],
            'issuer' => $this->issuer->toArray(),
            ...Credentials::period($validFrom, $validUntil),
            'name' => $name,
            'credentialSubject' => [
                'type' => ['ClrSubject'],
                ...$learner->toArray(),
                'verifiableCredential' => array_map($this->embedded(...), $credentials),
            ],
        ];

        return $partial ? [...$record, 'partial' => true] : $record;
    }

    /**
     * @param array<string, mixed>|string $credential
     *
     * @return array<string, mixed>
     */
    private function embedded(array|string $credential): array
    {
        if (\is_string($credential)) {
            if (1 !== preg_match('/^[\w-]+\.[\w-]+\.[\w-]*$/D', $credential)) {
                throw InvalidBadge::because('A credential given as a string to a learner record is a compact JSON Web Token.');
            }

            return ['@context' => Context::CREDENTIALS_V2, 'id' => 'data:application/vc+jwt,'.$credential, 'type' => 'EnvelopedVerifiableCredential'];
        }

        if (!\is_array($credential['proof'] ?? null) || !\in_array('VerifiableCredential', (array) ($credential['type'] ?? []), true)) {
            throw InvalidBadge::because('A credential given as a document to a learner record is a verifiable credential with its proof.');
        }

        return $credential;
    }
}
