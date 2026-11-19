<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\Http;

use Phil\VirtualClassroom\Exception\InvalidValue;

/**
 * What one call to a server may cost: the seconds without a byte before it gives up, the seconds the whole call may
 * take, and the largest answer read, in bytes. A slow or oversized answer is cut, never waited for.
 */
final readonly class HttpBudget
{
    /**
     * @throws InvalidValue when a bound is not positive, or the call is given less time than its silence
     */
    public function __construct(
        public float $timeout = 5.0,
        public float $maxDuration = 10.0,
        public int $maxBytes = 1_048_576,
    ) {
        if ($timeout <= 0 || $maxDuration < $timeout || $maxDuration > 120) {
            throw InvalidValue::because('A call waits more than 0 seconds for a byte, and as long or longer, up to 120 seconds, in all.');
        }

        if ($maxBytes < 1024 || $maxBytes > 16_777_216) {
            throw InvalidValue::because('An answer is read up to 1 KiB to 16 MiB.');
        }
    }
}
