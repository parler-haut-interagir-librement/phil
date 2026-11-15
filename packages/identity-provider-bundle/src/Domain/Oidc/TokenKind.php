<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Oidc;

/**
 * The two tokens the provider keeps a row of: access tokens by their `jti`, refresh tokens by the SHA-256 of their
 * value.
 */
enum TokenKind: string
{
    case Access = 'access';
    case Refresh = 'refresh';
}
