<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Exception;

use RuntimeException;

/**
 * A cell with dot 7 or dot 8, which a six-dot format such as BRF cannot write.
 */
final class UnrepresentableCell extends RuntimeException implements BrailleExportException
{
    public static function eightDots(int $page, int $row): self
    {
        return new self(\sprintf('Row %d of braille page %d holds a cell with dot 7 or 8, which BRF cannot write.', $row, $page));
    }
}
