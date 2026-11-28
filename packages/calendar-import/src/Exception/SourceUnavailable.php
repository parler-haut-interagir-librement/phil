<?php

declare(strict_types=1);

namespace Phil\CalendarImport\Exception;

use Phil\CalendarImport\Source\FailureKind;
use RuntimeException;
use Throwable;

/**
 * A source that gave no calendar this time: unreachable, refused, answering with an error, too large, or invalid. The
 * importer keeps the last state it read, and dates the failure.
 */
final class SourceUnavailable extends RuntimeException implements CalendarImportException
{
    public function __construct(
        public readonly FailureKind $kind,
        string $message,
        public readonly ?int $status = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    public static function of(FailureKind $kind, string $message, ?Throwable $previous = null): self
    {
        return new self($kind, $message, null, $previous);
    }

    public static function status(string $address, int $status): self
    {
        return new self(FailureKind::Status, \sprintf('"%s" answered with the status %d.', $address, $status), $status);
    }
}
