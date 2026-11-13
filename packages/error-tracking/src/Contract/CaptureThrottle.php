<?php

declare(strict_types=1);

namespace Phil\ErrorTracking\Contract;

/**
 * Bounds the events kept for one key in a span of time — a fingerprint, so that an error thrown in a loop writes a
 * bounded number of rows.
 */
interface CaptureThrottle
{
    /**
     * Whether one more event of the key may be kept now; asking counts it.
     */
    public function allow(string $key): bool;
}
