<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Application\Contract;

use DateTimeImmutable;
use Phil\LtiBundle\Application\StoredDeepLinkingReturn;

/**
 * The deep linking responses the platform verified, kept from before the application handles them until they expire:
 * a response whose handling failed is handled again from here, without its token, whose nonce was taken.
 */
interface DeepLinkingReturns
{
    /**
     * Keeps a response; the one kept under the same key stays as it is.
     */
    public function save(StoredDeepLinkingReturn $return): void;

    /**
     * The response kept under the key, null when there is none or it expired at `$now`.
     */
    public function find(string $key, DateTimeImmutable $now): ?StoredDeepLinkingReturn;

    /**
     * Records that the application handled the response; nothing when it is gone.
     */
    public function markHandled(string $key, DateTimeImmutable $at): void;

    /**
     * The responses not handled yet, received at `$before` or earlier and not expired at `$now`, oldest first.
     *
     * @param positive-int $limit
     *
     * @return list<StoredDeepLinkingReturn>
     */
    public function unhandled(DateTimeImmutable $before, DateTimeImmutable $now, int $limit): array;

    /**
     * Removes the responses expired at `$now`.
     *
     * @return int how many were removed
     */
    public function purge(DateTimeImmutable $now): int;
}
