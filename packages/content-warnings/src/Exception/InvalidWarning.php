<?php

declare(strict_types=1);

namespace Phil\ContentWarnings\Exception;

use InvalidArgumentException;

/**
 * A warning, a list of warnings or a preference that breaks a rule: a malformed theme key, a note too long, the same
 * theme twice, too many warnings.
 */
final class InvalidWarning extends InvalidArgumentException implements ContentWarningsException
{
    public static function because(string $reason): self
    {
        return new self(\sprintf('Invalid content warning: %s.', $reason));
    }
}
