<?php

declare(strict_types=1);

namespace Phil\Geo\Tests\Unit\Geocoding\MaxMind;

/**
 * A network's record already in the data section, at this offset.
 */
final readonly class MmdbOffset
{
    public function __construct(
        public int $offset,
    ) {
    }
}
