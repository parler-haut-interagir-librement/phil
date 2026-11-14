<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Support;

use DateTimeImmutable;

/**
 * The shape of an exported credential, checked offline against what the public text of Open Badges 3.0 describes for
 * an `OpenBadgeCredential` and its two securings, written out here as plain rules: no context document nor JSON
 * Schema of 1EdTech is read or kept. It lists the rules a credential breaks, none when it holds; it does not stand in
 * for 1EdTech's conformance validator, which also fetches what the credential points to.
 */
final class Ob3Shape
{
    public const string CREDENTIALS_CONTEXT = 'https://www.w3.org/ns/credentials/v2';

    public const string OPEN_BADGES_CONTEXT = 'https://purl.imsglobal.org/spec/ob/v3p0/context-3.0.3.json';

    private function __construct()
    {
    }

    /**
     * @param array<array-key, mixed> $credential
     *
     * @return list<string>
     */
    public static function problems(array $credential): array
    {
        $problems = [];
        $context = $credential['@context'] ?? null;

        if (!\is_array($context) || self::CREDENTIALS_CONTEXT !== ($context[0] ?? null) || !\in_array(self::OPEN_BADGES_CONTEXT, $context, true)) {
            $problems[] = '@context: the Verifiable Credentials 2.0 context first, then the Open Badges 3.0 one';
        }

        if (!self::isUri($credential['id'] ?? null)) {
            $problems[] = 'id: a URI';
        }

        if (!self::hasTypes($credential['type'] ?? null, 'VerifiableCredential') || !self::hasTypes($credential['type'] ?? null, 'OpenBadgeCredential') && !self::hasTypes($credential['type'] ?? null, 'AchievementCredential')) {
            $problems[] = 'type: VerifiableCredential and OpenBadgeCredential';
        }

        if (!self::isNonEmptyString($credential['name'] ?? null)) {
            $problems[] = 'name: a string';
        }

        $issuer = $credential['issuer'] ?? null;

        if (!\is_array($issuer) || !self::isUri($issuer['id'] ?? null) || !self::hasTypes($issuer['type'] ?? null, 'Profile')) {
            $problems[] = 'issuer: a Profile with its id';
        }

        if (!self::isMoment($credential['validFrom'] ?? null)) {
            $problems[] = 'validFrom: a date and time with its offset';
        }

        if (\array_key_exists('validUntil', $credential) && !self::isMoment($credential['validUntil'])) {
            $problems[] = 'validUntil: a date and time with its offset';
        }

        return [...$problems, ...self::subjectProblems($credential['credentialSubject'] ?? null), ...self::statusProblems($credential['credentialStatus'] ?? null)];
    }

    /**
     * What the header and the registered claims of a token break, against the credential they carry.
     *
     * @param array<array-key, mixed> $header
     * @param array<array-key, mixed> $claims
     *
     * @return list<string>
     */
    public static function tokenProblems(array $header, array $claims): array
    {
        $problems = [];

        if (!\in_array($header['alg'] ?? null, ['EdDSA', 'RS256'], true)) {
            $problems[] = 'alg: EdDSA or RS256';
        }

        if (!self::isNonEmptyString($header['kid'] ?? null) && !\is_array($header['jwk'] ?? null)) {
            $problems[] = 'kid or jwk: the key that verifies it';
        }

        $issuer = \is_array($claims['issuer'] ?? null) ? $claims['issuer']['id'] ?? null : null;

        if (($claims['iss'] ?? null) !== $issuer) {
            $problems[] = 'iss: the issuer of the credential';
        }

        if (($claims['jti'] ?? null) !== ($claims['id'] ?? false)) {
            $problems[] = 'jti: the id of the credential';
        }

        if (!\is_int($claims['nbf'] ?? null) || !\is_string($claims['validFrom'] ?? null) || new DateTimeImmutable($claims['validFrom'])->getTimestamp() !== $claims['nbf']) {
            $problems[] = 'nbf: the validFrom of the credential';
        }

        if (\is_string($claims['validUntil'] ?? null) && (!\is_int($claims['exp'] ?? null) || new DateTimeImmutable($claims['validUntil'])->getTimestamp() !== $claims['exp'])) {
            $problems[] = 'exp: the validUntil of the credential';
        }

        return $problems;
    }

    /**
     * What the proof of a document breaks.
     *
     * @param array<array-key, mixed> $document
     *
     * @return list<string>
     */
    public static function proofProblems(array $document): array
    {
        $proof = $document['proof'] ?? null;

        if (!\is_array($proof) || 'DataIntegrityProof' !== ($proof['type'] ?? null) || !\in_array($proof['cryptosuite'] ?? null, ['eddsa-rdfc-2022', 'eddsa-jcs-2022'], true)) {
            return ['proof: a DataIntegrityProof of an EdDSA cryptosuite'];
        }

        $problems = [];

        if ('assertionMethod' !== ($proof['proofPurpose'] ?? null)) {
            $problems[] = 'proof.proofPurpose: assertionMethod';
        }

        $issuer = \is_array($document['issuer'] ?? null) ? $document['issuer']['id'] ?? null : null;

        if (!\is_string($issuer) || !\is_string($proof['verificationMethod'] ?? null) || !str_starts_with($proof['verificationMethod'], $issuer.'#')) {
            $problems[] = 'proof.verificationMethod: a key of the issuer';
        }

        if (!self::isMoment($proof['created'] ?? null)) {
            $problems[] = 'proof.created: a date and time with its offset';
        }

        if (!\is_string($proof['proofValue'] ?? null) || 1 !== preg_match('/^z[1-9A-HJ-NP-Za-km-z]+$/D', $proof['proofValue'])) {
            $problems[] = 'proof.proofValue: base 58, starting with "z"';
        }

        return $problems;
    }

    /**
     * @return list<string>
     */
    private static function subjectProblems(mixed $subject): array
    {
        if (!\is_array($subject) || !self::hasTypes($subject['type'] ?? null, 'AchievementSubject')) {
            return ['credentialSubject: an AchievementSubject'];
        }

        $problems = [];
        $identifiers = $subject['identifier'] ?? null;

        if (!self::isUri($subject['id'] ?? null) && (!\is_array($identifiers) || [] === $identifiers)) {
            $problems[] = 'credentialSubject: an id or an identifier';
        }

        foreach (\is_array($identifiers) ? $identifiers : [] as $identifier) {
            if (!\is_array($identifier) || 'IdentityObject' !== ($identifier['type'] ?? null) || !\is_bool($identifier['hashed'] ?? null) || !\is_string($identifier['identityHash'] ?? null) || !\is_string($identifier['identityType'] ?? null)) {
                $problems[] = 'credentialSubject.identifier: IdentityObjects with hashed, identityHash and identityType';

                continue;
            }

            if ($identifier['hashed'] && (1 !== preg_match('/^sha256\$[0-9a-f]{64}$/D', $identifier['identityHash']) || !\is_string($identifier['salt'] ?? null))) {
                $problems[] = 'credentialSubject.identifier: a hashed identity is sha256$ and 64 hexadecimal digits, with its salt';
            }
        }

        $achievement = $subject['achievement'] ?? null;

        if (!\is_array($achievement) || !self::isUri($achievement['id'] ?? null) || !self::hasTypes($achievement['type'] ?? null, 'Achievement') || !self::isNonEmptyString($achievement['name'] ?? null) || !\is_string($achievement['description'] ?? null)) {
            return [...$problems, 'credentialSubject.achievement: an Achievement with its id, name and description'];
        }

        $criteria = $achievement['criteria'] ?? null;

        if (!\is_array($criteria) || !self::isUri($criteria['id'] ?? null) && !self::isNonEmptyString($criteria['narrative'] ?? null)) {
            $problems[] = 'credentialSubject.achievement.criteria: an id or a narrative';
        }

        $image = $achievement['image'] ?? null;

        if (null !== $image && (!\is_array($image) || !self::isUri($image['id'] ?? null) || 'Image' !== ($image['type'] ?? null))) {
            $problems[] = 'credentialSubject.achievement.image: an Image with its id';
        }

        return $problems;
    }

    /**
     * @return list<string>
     */
    private static function statusProblems(mixed $status): array
    {
        if (null === $status) {
            return [];
        }

        if (!\is_array($status) || 'BitstringStatusListEntry' !== ($status['type'] ?? null) || !\in_array($status['statusPurpose'] ?? null, ['revocation', 'suspension'], true)
            || !\is_string($status['statusListIndex'] ?? null) || 1 !== preg_match('/^(?:0|[1-9]\d*)$/D', $status['statusListIndex'])
            || !self::isUri($status['statusListCredential'] ?? null)) {
            return ['credentialStatus: a BitstringStatusListEntry, its index a string of digits'];
        }

        return [];
    }

    private static function isUri(mixed $value): bool
    {
        return \is_string($value) && 1 === preg_match('/^[a-z][a-z0-9+.-]*:\S+$/iD', $value);
    }

    private static function isNonEmptyString(mixed $value): bool
    {
        return \is_string($value) && '' !== trim($value);
    }

    private static function isMoment(mixed $value): bool
    {
        return \is_string($value) && 1 === preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/D', $value);
    }

    private static function hasTypes(mixed $types, string $type): bool
    {
        return $types === $type || \is_array($types) && \in_array($type, $types, true);
    }
}
