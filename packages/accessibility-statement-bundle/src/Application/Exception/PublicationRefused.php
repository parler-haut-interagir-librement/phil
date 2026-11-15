<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Application\Exception;

use Phil\AccessibilityStatement\Exception\AccessibilityStatementException;
use Phil\AccessibilityStatementBundle\Domain\Exception\StatementNotStored;
use RuntimeException;
use Throwable;

/**
 * A version the administration page does not publish: a document that does not read as a statement, a number other
 * than the next one, or a store that keeps no version. The message says why, for the person publishing.
 */
final class PublicationRefused extends RuntimeException implements AccessibilityStatementException
{
    public static function unreadable(Throwable $previous): self
    {
        return new self('The document does not read as a statement: '.$previous->getMessage(), previous: $previous);
    }

    public static function notNext(int $version, int $next): self
    {
        return new self(\sprintf('The version to publish is %d, not %d.', $next, $version));
    }

    public static function notStored(StatementNotStored $previous): self
    {
        return new self($previous->getMessage(), previous: $previous);
    }
}
