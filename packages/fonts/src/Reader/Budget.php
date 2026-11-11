<?php

declare(strict_types=1);

namespace Phil\Fonts\Reader;

use Phil\Fonts\Exception\InvalidFont;

/**
 * The bytes the tables of one font file may come to, copied or decompressed: each table is paid for before it is read.
 *
 * @internal
 */
final class Budget
{
    public function __construct(
        private int $left,
    ) {
    }

    public function spend(int $bytes): void
    {
        if ($bytes > $this->left) {
            throw InvalidFont::because('The tables of the font file come to more than 64 MiB.');
        }

        $this->left -= $bytes;
    }
}
