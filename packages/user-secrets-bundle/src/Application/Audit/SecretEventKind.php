<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Application\Audit;

/**
 * What happened to a secret.
 */
enum SecretEventKind: string
{
    /** A value was stored, new or replacing the previous one. */
    case Stored = 'stored';
    /** A task of the owner read the value. */
    case Revealed = 'revealed';
    /** An expired OAuth token was refreshed and the new one stored. */
    case Refreshed = 'refreshed';
    /** The owner revoked the secret and its value was wiped. */
    case Revoked = 'revoked';
    /** The secret's row was removed. */
    case Removed = 'removed';
    /** The secret was removed with the rest of its owner's secrets, when the owner's account was erased. */
    case Erased = 'erased';
    /** The value was sealed again under the current key. */
    case Rekeyed = 'rekeyed';
    /** The service that issued the OAuth token revoked it, after it was revoked, removed or erased locally. */
    case RevokedAtService = 'revoked_at_service';
    /** The service did not revoke the OAuth token; it was gone locally all the same, and the failure was logged. */
    case ServiceRevocationFailed = 'service_revocation_failed';
}
