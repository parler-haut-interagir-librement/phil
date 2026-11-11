<?php

declare(strict_types=1);

namespace Phil\Checkin;

/**
 * Whether a credential still opens its checkpoints: a refunded ticket, a cancelled registration or a banned holder is
 * revoked, and its scans are refused as such rather than as unknown.
 */
enum CredentialStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
}
