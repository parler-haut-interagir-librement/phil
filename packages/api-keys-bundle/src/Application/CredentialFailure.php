<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Application;

/**
 * Why a presented token was refused. The client is never told which: the reason is for the application's logs.
 */
enum CredentialFailure: string
{
    case Malformed = 'malformed';
    case Unknown = 'unknown';
    case Revoked = 'revoked';
    case Expired = 'expired';
}
