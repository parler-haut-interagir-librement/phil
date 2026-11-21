<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\WebAuthn;

/**
 * What an attestation statement proved about the authenticator of a passkey:
 *
 * - `none`: no statement, the format `none`: nothing is known of the authenticator, its AAGUID included;
 * - `self`: a `packed` statement signed by the passkey itself: the passkey holds its key, nothing more;
 * - `unverified`: a statement of a format the relying party does not read, or a certificate chain that reaches no
 *   trusted root: the AAGUID is what the authenticator claims;
 * - `trusted`: a `packed` statement signed by a certificate whose chain reaches a root the relying party trusts: the
 *   AAGUID is the make and model the root's vendor vouches for.
 */
enum AttestationType: string
{
    case None = 'none';
    case Self = 'self';
    case Unverified = 'unverified';
    case Trusted = 'trusted';

    public const int MAX_LENGTH = 16;
}
