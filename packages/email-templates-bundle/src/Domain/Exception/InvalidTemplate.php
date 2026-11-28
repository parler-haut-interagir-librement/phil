<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A template refused: a key, a site, a locale or a field out of shape, a variable schema that does not hold, a source
 * the sandbox refuses or that does not render with its sample values. Every reason is listed in `errors`.
 */
final class InvalidTemplate extends InvalidArgumentException implements EmailTemplatesException
{
    /**
     * @param non-empty-list<string> $errors
     */
    public function __construct(
        public readonly array $errors,
    ) {
        parent::__construct(implode(' ', $errors));
    }

    public static function because(string $reason, string ...$more): self
    {
        return new self([$reason, ...array_values($more)]);
    }

    /**
     * @param non-empty-list<string> $errors
     */
    public static function withErrors(array $errors): self
    {
        return new self($errors);
    }
}
