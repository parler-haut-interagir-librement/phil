<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Exception;

use RuntimeException;

/**
 * A whole file refused before any row is planned: too large, too many rows or columns, not in its declared encoding,
 * without a header, or with a quote left open.
 */
final class SourceRefused extends RuntimeException implements ImportWizardException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
