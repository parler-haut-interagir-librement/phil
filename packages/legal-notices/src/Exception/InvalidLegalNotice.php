<?php

declare(strict_types=1);

namespace Phil\LegalNotices\Exception;

use InvalidArgumentException;
use Throwable;

/**
 * A value the legal notices refuse: a field of the identity, a version, a history, a duration or a consent category
 * that breaks a rule. Its reason is a short stable code — `unknown_field`, `effective_before_publication` — that a
 * caller may branch on, the message saying the same for a person.
 */
final class InvalidLegalNotice extends InvalidArgumentException
{
    public function __construct(
        string $message,
        public readonly string $reason,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function because(string $reason, string $message): self
    {
        return new self($message, $reason);
    }

    /**
     * A value as a message quotes it: between double quotes, cut to its first 80 characters.
     */
    public static function quote(string $value): string
    {
        return '"'.mb_substr($value, 0, 80).'"';
    }
}
