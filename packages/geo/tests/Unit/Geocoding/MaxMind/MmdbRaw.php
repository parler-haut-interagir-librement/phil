<?php

declare(strict_types=1);

namespace Phil\Geo\Tests\Unit\Geocoding\MaxMind;

/**
 * Bytes the writer copies as they are: a value the encoder does not make, or a broken one.
 */
final readonly class MmdbRaw
{
    public function __construct(
        public string $bytes,
    ) {
    }
}
