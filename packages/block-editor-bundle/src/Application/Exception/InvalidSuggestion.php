<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Application\Exception;

use InvalidArgumentException;

/**
 * A suggestion of a reference search names its target in a form a mention or a page link cannot hold.
 */
final class InvalidSuggestion extends InvalidArgumentException implements BlockEditorException
{
    public static function reference(string $reference): self
    {
        return new self(\sprintf('The reference "%s" is not of the form kind:identifier, a kind of a lower-case letter then up to 31 of a-z, 0-9, "_" or "-", an identifier of 1 to 128 of A-Z, a-z, 0-9, "_", ".", "/" or "-".', $reference));
    }

    public static function label(): self
    {
        return new self('A suggestion needs a label of 1 to 200 characters.');
    }
}
