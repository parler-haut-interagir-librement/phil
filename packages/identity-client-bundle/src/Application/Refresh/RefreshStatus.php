<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Application\Refresh;

enum RefreshStatus: string
{
    /** The provider rotated the tokens. */
    case Refreshed = 'refreshed';
    /** The provider no longer honours the refresh token. */
    case Revoked = 'revoked';
    /** The provider could not be reached or answered with an error; the tokens stay as they are. */
    case Unavailable = 'unavailable';
}
