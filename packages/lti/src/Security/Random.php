<?php

declare(strict_types=1);

namespace Phil\Lti\Security;

use Phil\Lti\Jwt\Base64Url;

/**
 * Unpredictable values for states, nonces, token identifiers and access tokens: 32 random bytes in URL-safe base 64.
 *
 * @internal
 */
final class Random
{
    public static function token(): string
    {
        return Base64Url::encode(random_bytes(32));
    }
}
