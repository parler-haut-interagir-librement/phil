<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer\Contract;

use SensitiveParameter;

/**
 * Where the servers of an application share the random salt of a period, for as long as the period lasts.
 *
 * **The expiry is what forgets the salt.** The store must not outlive it: no append-only file, no snapshot, no backup
 * of the entry, or the salts it was meant to forget can link the digests of two periods again.
 */
interface ExpiringSaltStore
{
    /**
     * The bytes stored under this identifier. When there are none, `$candidate` is stored, for at most `$ttlSeconds`,
     * and returned: this happens atomically, so that two servers that find the entry missing end up with the same
     * bytes.
     *
     * @param positive-int $ttlSeconds
     */
    public function claim(string $saltId, #[SensitiveParameter] string $candidate, int $ttlSeconds): string;
}
