<?php

declare(strict_types=1);

namespace Phil\DocumentImport;

/**
 * One kind of thing an import left out, with what it is precisely — `chart`, `colour`, `script` — and how many times
 * it was met.
 */
final readonly class Omission
{
    public function __construct(
        public Omitted $what,
        public string $detail,
        public int $count,
    ) {
    }
}
