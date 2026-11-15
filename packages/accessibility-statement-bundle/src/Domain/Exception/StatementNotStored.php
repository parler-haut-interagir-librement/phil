<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Domain\Exception;

use Phil\AccessibilityStatement\Exception\AccessibilityStatementException;
use RuntimeException;
use Throwable;

/**
 * A version that could not be published: the statements come from the configuration, or the number is taken.
 */
final class StatementNotStored extends RuntimeException implements AccessibilityStatementException
{
    public static function readOnly(): self
    {
        return new self('The statement comes from the configuration: a new version is written there.');
    }

    public static function versionTaken(int $version, ?Throwable $previous = null): self
    {
        return new self(\sprintf('Version %d of the statement is already published.', $version), previous: $previous);
    }
}
