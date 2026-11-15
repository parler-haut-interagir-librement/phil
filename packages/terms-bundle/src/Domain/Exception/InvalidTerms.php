<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Domain\Exception;

use InvalidArgumentException;
use Throwable;

/**
 * A value or an operation the terms refuse: an identifier of the wrong shape, a version that breaks a rule, an
 * acceptance of a version that is not the one to accept. Its reason is a translation key of the `phil_terms` domain,
 * prefixed by `error.`, which a page shows with its parameters.
 */
final class InvalidTerms extends InvalidArgumentException
{
    /**
     * @param array<string, string> $parameters
     */
    public function __construct(
        string $message,
        public readonly string $reason,
        public readonly array $parameters = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @param array<string, string> $parameters
     */
    public static function because(string $reason, string $message, array $parameters = []): self
    {
        return new self(strtr($message, $parameters), $reason, $parameters);
    }
}
