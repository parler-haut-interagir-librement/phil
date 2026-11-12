<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Domain\Exception;

use InvalidArgumentException;

/**
 * A key, a structure version, a revision or a content that a draft cannot be made of.
 */
final class InvalidDraft extends InvalidArgumentException implements AutosaveException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
