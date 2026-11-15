<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\WebAuthn;

use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;

/**
 * The authenticator data of a WebAuthn response: the hash of the relying party identifier, the flags — user present,
 * user verified, backup eligible and backed up, attested credential data, extensions —, the signature counter, and at
 * registration the credential: its AAGUID, identifier and COSE public key.
 */
final readonly class AuthenticatorData
{
    public const int USER_PRESENT = 0x01;

    public const int USER_VERIFIED = 0x04;

    public const int BACKUP_ELIGIBLE = 0x08;

    public const int BACKED_UP = 0x10;

    public const int ATTESTED_CREDENTIAL = 0x40;

    public const int EXTENSIONS = 0x80;

    /** The longest credential identifier WebAuthn allows. */
    public const int MAX_CREDENTIAL_ID = 1023;

    private function __construct(
        public string $rpIdHash,
        public int $flags,
        public int $signCount,
        public ?string $aaguid,
        public ?string $credentialId,
        public ?string $coseKey,
    ) {
    }

    /**
     * @throws MalformedInput when the bytes are cut short, the credential is out of bounds, or bytes are left over
     */
    public static function parse(string $bytes): self
    {
        $length = \strlen($bytes);

        if ($length < 37) {
            throw MalformedInput::because('The authenticator data holds at least 37 bytes.');
        }

        $flags = \ord($bytes[32]);
        $signCount = (\ord($bytes[33]) << 24) | (\ord($bytes[34]) << 16) | (\ord($bytes[35]) << 8) | \ord($bytes[36]);
        $offset = 37;
        [$aaguid, $credentialId, $coseKey] = [null, null, null];

        if (0 !== ($flags & self::ATTESTED_CREDENTIAL)) {
            if ($length < $offset + 18) {
                throw MalformedInput::because('The attested credential data is cut short.');
            }

            $aaguid = substr($bytes, $offset, 16);
            $idLength = (\ord($bytes[$offset + 16]) << 8) | \ord($bytes[$offset + 17]);
            $offset += 18;

            if (0 === $idLength || $idLength > self::MAX_CREDENTIAL_ID || $length < $offset + $idLength) {
                throw MalformedInput::because('The credential identifier is empty, too long or cut short.');
            }

            $credentialId = substr($bytes, $offset, $idLength);
            $offset += $idLength;
            [$key, $end] = Cbor::decodeAt($bytes, $offset);

            if (!\is_array($key)) {
                throw MalformedInput::because('The credential public key is a COSE map.');
            }

            $coseKey = substr($bytes, $offset, $end - $offset);
            $offset = $end;
        }

        if (0 !== ($flags & self::EXTENSIONS)) {
            [, $offset] = Cbor::decodeAt($bytes, $offset);
        }

        if ($offset !== $length) {
            throw MalformedInput::because('The authenticator data holds bytes after its last part.');
        }

        return new self(substr($bytes, 0, 32), $flags, $signCount, $aaguid, $credentialId, $coseKey);
    }

    public function has(int $flag): bool
    {
        return 0 !== ($this->flags & $flag);
    }
}
