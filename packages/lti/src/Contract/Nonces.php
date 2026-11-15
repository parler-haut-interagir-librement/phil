<?php

declare(strict_types=1);

namespace Phil\Lti\Contract;

use DateTimeImmutable;

/**
 * Values that may be used once: the nonce of an `id_token`, of a deep linking response, the `jti` of a client
 * assertion. Each is remembered until it expires; a value seen again before then is a replay.
 */
interface Nonces
{
    /**
     * Remembers a value of a scope until it expires; false when it was already remembered and has not expired. The check
     * and the write are one step: two requests with the same value never both succeed.
     */
    public function use(string $scope, string $value, DateTimeImmutable $expiresAt): bool;
}
