<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Application\Exception;

use Phil\FormsAutosave\Domain\Exception\AutosaveException;
use RuntimeException;

/**
 * A draft larger than the server keeps.
 */
final class DraftTooLarge extends RuntimeException implements AutosaveException
{
    public static function above(int $bytes): self
    {
        return new self(\sprintf('A draft is at most %d bytes.', $bytes));
    }
}
