<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Contract;

/**
 * Bounds the pace of the requests to each host: `wait()` returns when a request to the host may be sent, sleeping until
 * then if it must. `IntervalThrottle` keeps the pace in memory, for one process.
 */
interface HostThrottle
{
    public function wait(string $host): void;
}
