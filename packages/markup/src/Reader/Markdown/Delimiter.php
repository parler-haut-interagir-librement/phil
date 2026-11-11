<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Markdown;

/**
 * A run of the same delimiter character — `*`, `_`, `~`, `=`, `^` — that may open or close an emphasis, as the
 * flanking rules of CommonMark decide. Pairing consumes it.
 */
final class Delimiter
{
    public function __construct(
        public readonly string $character,
        public int $length,
        public readonly bool $opens,
        public readonly bool $closes,
    ) {
    }
}
