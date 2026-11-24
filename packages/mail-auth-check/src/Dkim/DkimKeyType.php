<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Dkim;

/**
 * The key types of DKIM: RSA (RFC 6376) and Ed25519 (RFC 8463).
 */
enum DkimKeyType: string
{
    case Rsa = 'rsa';
    case Ed25519 = 'ed25519';
}
