<?php

declare(strict_types=1);

namespace Phil\TimedText\Exception;

use Throwable;
use UnexpectedValueException;

/**
 * A document a reader refuses, with the line where it stopped when the format has lines.
 */
final class InvalidTimedText extends UnexpectedValueException implements TimedTextException
{
    public function __construct(
        string $message,
        public readonly ?int $documentLine = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function at(?int $line, string $message, ?Throwable $previous = null): self
    {
        return new self(null === $line ? $message : \sprintf('Line %d: %s', $line, $message), $line, $previous);
    }
}
