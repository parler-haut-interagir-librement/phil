<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\WebAuthn;

use OpenSSLCertificate;
use Phil\IdentityProviderBundle\Domain\Encoding\Der;
use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;
use Phil\IdentityProviderBundle\Domain\Exception\VerificationFailed;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;

/**
 * The attestation statement of a registration (WebAuthn Level 3, section 8), read for the formats `none` and `packed`:
 *
 * - `none` carries nothing;
 * - `packed` without certificates is signed by the passkey itself, with the algorithm of its key;
 * - `packed` with certificates (`x5c`, at most MAX_CERTIFICATES) is signed by the first, a certificate of version 3
 *   whose subject's organizational unit is "Authenticator Attestation", that is no authority, and whose AAGUID
 *   extension, when present, is the octet string of the AAGUID of the passkey. The statement is `trusted` when each certificate is
 *   signed by the next, the last by one of the trusted roots, and all are valid at the time given; `unverified`
 *   otherwise.
 *
 * Another format is `unverified`: its statement is not read. A signature that does not verify, or a certificate out of
 * shape, refuses the registration: an authenticator that sends a statement sends a true one.
 */
final readonly class AttestationStatement
{
    public const int MAX_CERTIFICATES = 5;

    /** The extension of an attestation certificate that names the AAGUID: id-fido-gen-ce-aaguid. */
    public const string AAGUID_EXTENSION = '1.3.6.1.4.1.45724.1.1.4';

    private function __construct()
    {
    }

    /**
     * @param list<string> $trustedRoots PEM certificates
     *
     * @throws MalformedInput     when the statement is out of shape
     * @throws VerificationFailed when its signature does not verify, or its certificate breaks a rule of the format
     */
    public static function verify(string $format, mixed $statement, string $authData, string $clientDataHash, AuthenticatorData $data, array $trustedRoots, int $now): AttestationType
    {
        if (!\is_array($statement)) {
            throw MalformedInput::because('An attestation statement is a map.');
        }

        return match ($format) {
            'none' => [] === $statement ? AttestationType::None : throw MalformedInput::because('The statement of the format none is empty.'),
            'packed' => self::packed($statement, $authData.$clientDataHash, $data, $trustedRoots, $now),
            default => AttestationType::Unverified,
        };
    }

    /**
     * @param array<array-key, mixed> $statement
     * @param list<string>            $trustedRoots
     */
    private static function packed(array $statement, string $signed, AuthenticatorData $data, array $trustedRoots, int $now): AttestationType
    {
        $alg = $statement['alg'] ?? null;
        $signature = $statement['sig'] ?? null;
        $algorithm = \is_int($alg) ? SigningAlgorithm::fromCose($alg) : null;

        if (!$algorithm instanceof SigningAlgorithm || !\is_string($signature) || '' === $signature) {
            throw MalformedInput::because('A packed statement has a known algorithm and a signature.');
        }

        if (null === $data->coseKey || null === $data->aaguid) {
            throw MalformedInput::because('A packed statement comes with a credential.');
        }

        if (!\array_key_exists('x5c', $statement)) {
            $key = CoseKey::decode($data->coseKey);

            if ($key->algorithm !== $algorithm || !$key->verify($signed, $signature)) {
                throw VerificationFailed::because('The self attestation is not signed by the passkey with its algorithm.');
            }

            return AttestationType::Self;
        }

        $chain = self::chain($statement['x5c']);

        if (SigningAlgorithm::EdDSA === $algorithm) {
            return AttestationType::Unverified;
        }

        if (1 !== openssl_verify($signed, $signature, $chain[0], \OPENSSL_ALGO_SHA256)) {
            throw VerificationFailed::because('The attestation is not signed by its certificate.');
        }

        self::assertAttestationCertificate($chain[0], $data->aaguid);

        return self::reachesRoot($chain, $trustedRoots, $now) ? AttestationType::Trusted : AttestationType::Unverified;
    }

    /**
     * @return non-empty-list<OpenSSLCertificate>
     */
    private static function chain(mixed $x5c): array
    {
        if (!\is_array($x5c) || [] === $x5c || \count($x5c) > self::MAX_CERTIFICATES || !array_is_list($x5c)) {
            throw MalformedInput::because(\sprintf('The certificates of a statement are a list of 1 to %d.', self::MAX_CERTIFICATES));
        }

        $chain = [];

        foreach ($x5c as $der) {
            $certificate = \is_string($der) && '' !== $der ? self::read(Der::certificate($der)) : null;
            $chain[] = $certificate ?? throw MalformedInput::because('A certificate of the statement is not DER.');
        }

        return $chain;
    }

    private static function assertAttestationCertificate(OpenSSLCertificate $certificate, string $aaguid): void
    {
        $parsed = openssl_x509_parse($certificate, false);
        $subject = \is_array($parsed) && \is_array($parsed['subject'] ?? null) ? $parsed['subject'] : [];
        $extensions = \is_array($parsed) && \is_array($parsed['extensions'] ?? null) ? $parsed['extensions'] : [];
        $constraints = $extensions['basicConstraints'] ?? '';
        $named = $extensions[self::AAGUID_EXTENSION] ?? null;

        if (!\is_array($parsed) || 2 !== ($parsed['version'] ?? null) || 'Authenticator Attestation' !== ($subject['organizationalUnitName'] ?? null)
            || !\is_string($constraints) || !str_contains($constraints, 'CA:FALSE')) {
            throw VerificationFailed::because('The attestation certificate is not of version 3, of the unit "Authenticator Attestation", and no authority.');
        }

        if (null !== $named && (!\is_string($named) || !hash_equals("\x04\x10".$aaguid, $named))) {
            throw VerificationFailed::because('The attestation certificate names another AAGUID.');
        }
    }

    /**
     * @param non-empty-list<OpenSSLCertificate> $chain
     * @param list<string>                       $trustedRoots
     */
    private static function reachesRoot(array $chain, array $trustedRoots, int $now): bool
    {
        foreach ($chain as $index => $certificate) {
            $issuer = $chain[$index + 1] ?? null;

            if (!self::isValidAt($certificate, $now) || ($issuer instanceof OpenSSLCertificate && (!self::isAuthority($issuer) || 1 !== openssl_x509_verify($certificate, $issuer)))) {
                return false;
            }
        }

        $last = $chain[\count($chain) - 1];

        foreach ($trustedRoots as $pem) {
            $root = self::read($pem);

            if ($root instanceof OpenSSLCertificate && self::isValidAt($root, $now) && 1 === openssl_x509_verify($last, $root)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The certificate of a PEM, null when OpenSSL does not read one; its warning is left out, the null says it.
     */
    private static function read(string $pem): ?OpenSSLCertificate
    {
        $certificate = @openssl_x509_read($pem);

        return $certificate instanceof OpenSSLCertificate ? $certificate : null;
    }

    private static function isValidAt(OpenSSLCertificate $certificate, int $now): bool
    {
        $parsed = openssl_x509_parse($certificate);
        $from = \is_array($parsed) ? $parsed['validFrom_time_t'] ?? null : null;
        $to = \is_array($parsed) ? $parsed['validTo_time_t'] ?? null : null;

        return \is_int($from) && \is_int($to) && $from <= $now && $now <= $to;
    }

    private static function isAuthority(OpenSSLCertificate $certificate): bool
    {
        $parsed = openssl_x509_parse($certificate);
        $constraints = \is_array($parsed) && \is_array($parsed['extensions'] ?? null) ? $parsed['extensions']['basicConstraints'] ?? null : null;

        return \is_string($constraints) && str_contains($constraints, 'CA:TRUE');
    }
}
