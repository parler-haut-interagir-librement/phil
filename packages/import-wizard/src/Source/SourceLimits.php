<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Source;

use Phil\ImportWizard\Exception\InvalidProfile;

/**
 * The bounds of a file read: its size, its rows after the header and the cells of a row. A file beyond them is refused
 * whole, before any row is planned, so that the memory and the time an import takes stay bounded.
 */
final readonly class SourceLimits
{
    /** @var positive-int */
    public int $maxBytes;

    /** @var positive-int */
    public int $maxRows;

    /** @var positive-int */
    public int $maxColumns;

    public function __construct(int $maxBytes = 20 * 1024 * 1024, int $maxRows = 100_000, int $maxColumns = 256)
    {
        if ($maxBytes < 1 || $maxRows < 1 || $maxColumns < 1) {
            throw InvalidProfile::because('The limits of a source must be positive.');
        }

        $this->maxBytes = $maxBytes;
        $this->maxRows = $maxRows;
        $this->maxColumns = $maxColumns;
    }
}
