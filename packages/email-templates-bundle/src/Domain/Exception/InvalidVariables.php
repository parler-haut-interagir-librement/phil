<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * Values that do not match the variable schema of a template: each violation names the path of the variable, as
 * `event.starts_at` or `items.2.name`, and what is wrong with it. The values themselves are never repeated, since they
 * may hold personal data.
 */
final class InvalidVariables extends InvalidArgumentException implements EmailTemplatesException
{
    /**
     * @param non-empty-array<string, string> $violations the reason of each path
     */
    public function __construct(
        public readonly array $violations,
    ) {
        $lines = [];

        foreach ($violations as $path => $reason) {
            $lines[] = \sprintf('"%s" %s', $path, $reason);
        }

        parent::__construct('The variables do not match the schema: '.implode('; ', $lines).'.');
    }

    /**
     * @param non-empty-array<string, string> $violations
     */
    public static function withViolations(array $violations): self
    {
        return new self($violations);
    }
}
