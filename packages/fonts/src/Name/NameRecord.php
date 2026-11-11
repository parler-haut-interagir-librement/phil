<?php

declare(strict_types=1);

namespace Phil\Fonts\Name;

/**
 * One string of a `name` table, decoded to UTF-8, with the platform, encoding and language it was written for.
 */
final readonly class NameRecord
{
    public function __construct(
        public int $platform,
        public int $encoding,
        public int $language,
        public int $nameId,
        public string $value,
    ) {
    }
}
