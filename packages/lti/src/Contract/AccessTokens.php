<?php

declare(strict_types=1);

namespace Phil\Lti\Contract;

use Phil\Lti\Platform\Token\AccessToken;

/**
 * The access tokens the platform granted.
 */
interface AccessTokens
{
    public function save(AccessToken $token): void;

    /**
     * The token of this hash, or null when it is unknown or expired.
     */
    public function find(string $hash): ?AccessToken;
}
