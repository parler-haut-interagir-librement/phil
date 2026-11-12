<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Application\Contract;

/**
 * How many report bodies a client may post: the bundle's adapters read a limiter of phil/rate-limit-bundle or of
 * `symfony/rate-limiter`.
 */
interface ReportThrottle
{
    /**
     * Whether one more body of this client is accepted now, counting it when it is.
     *
     * @param string $client a keyed digest of the client's address, never the address itself
     */
    public function allow(string $client): bool;
}
