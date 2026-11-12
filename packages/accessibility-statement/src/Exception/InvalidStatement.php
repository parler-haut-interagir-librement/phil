<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement\Exception;

use InvalidArgumentException;

/**
 * A statement, or one of its parts, that cannot be published: a value out of its bounds, a status the non-conformities
 * contradict, a criterion outside the target, data that does not read as a statement. The reason is a short key an
 * application may translate.
 */
final class InvalidStatement extends InvalidArgumentException implements AccessibilityStatementException
{
    public function __construct(
        string $message,
        public readonly string $reason,
    ) {
        parent::__construct($message);
    }

    public static function because(string $reason, string $message): self
    {
        return new self($message, $reason);
    }
}
