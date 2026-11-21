<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\WebAuthn;

use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Exception\VerificationFailed;

/**
 * What the relying party asks of the authenticators of new passkeys:
 *
 * - `conveyance`: the attestation the browser is asked for, `none` by default, `indirect`, `direct` or `enterprise`;
 * - `trustedRoots`: the PEM certificates of the roots a `packed` statement may chain to;
 * - `requireTrusted`: whether a passkey whose statement reaches none of them is refused;
 * - `allowedAaguids` and `deniedAaguids`: the makes and models accepted and refused, by AAGUID — an AAGUID is what the
 *   authenticator claims unless `requireTrusted` is on, so a list without it keeps honest authenticators out, not a
 *   forged one;
 * - `backup`: whether a passkey that may be synced to other devices — backup eligible — is `allowed`, `required` or
 *   `forbidden`.
 *
 * The flags of eligibility and backup state are recorded on every passkey whatever the policy.
 */
final readonly class AttestationPolicy
{
    public const array CONVEYANCES = ['none', 'indirect', 'direct', 'enterprise'];

    public const array BACKUP = ['allowed', 'required', 'forbidden'];

    /** The AAGUID of an authenticator that names none: sixteen zero bytes. */
    public const string NO_AAGUID = '00000000-0000-0000-0000-000000000000';

    /**
     * @param list<string> $trustedRoots   PEM certificates
     * @param list<string> $allowedAaguids AAGUIDs in their 36 characters, any case; empty for any
     * @param list<string> $deniedAaguids  AAGUIDs in their 36 characters, any case
     */
    public function __construct(
        public string $conveyance = 'none',
        public array $trustedRoots = [],
        public bool $requireTrusted = false,
        public array $allowedAaguids = [],
        public array $deniedAaguids = [],
        public string $backup = 'allowed',
    ) {
        if (!\in_array($conveyance, self::CONVEYANCES, true) || !\in_array($backup, self::BACKUP, true)) {
            throw InvalidValue::because('The conveyance is none, indirect, direct or enterprise, and the backup allowed, required or forbidden.');
        }

        if ($requireTrusted && ([] === $trustedRoots || 'none' === $conveyance)) {
            throw InvalidValue::because('A trusted attestation needs trusted roots and a conveyance other than none.');
        }

        foreach ([...$allowedAaguids, ...$deniedAaguids] as $aaguid) {
            if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/Di', $aaguid)) {
                throw InvalidValue::because(\sprintf('"%s" is not an AAGUID of 36 characters.', $aaguid));
            }
        }
    }

    /**
     * The AAGUID of 16 bytes in its 36 characters, lower case.
     */
    public static function format(string $aaguid): string
    {
        $hex = sodium_bin2hex($aaguid);

        return \sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }

    /**
     * @throws VerificationFailed when the passkey breaks the policy
     */
    public function assert(string $aaguid, AttestationType $type, bool $backupEligible): void
    {
        if ($this->requireTrusted && AttestationType::Trusted !== $type) {
            throw VerificationFailed::because('The attestation of the passkey reaches no trusted root.');
        }

        $aaguid = self::format($aaguid);

        if (\in_array($aaguid, array_map(strtolower(...), $this->deniedAaguids), true)
            || ([] !== $this->allowedAaguids && !\in_array($aaguid, array_map(strtolower(...), $this->allowedAaguids), true))) {
            throw VerificationFailed::because(\sprintf('The authenticator %s is not accepted.', $aaguid));
        }

        if (('forbidden' === $this->backup && $backupEligible) || ('required' === $this->backup && !$backupEligible)) {
            throw VerificationFailed::because(\sprintf('A passkey that may be synced is %s.', $this->backup));
        }
    }
}
