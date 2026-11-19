<?php

declare(strict_types=1);

namespace Phil\Ujo\Exception;

use InvalidArgumentException;

/**
 * An HTML fragment that holds more tags than the inline sanitizer reads, or nests its elements deeper than it allows,
 * refused as soon as the budget is spent: nothing past it is read, and a fragment is never cut to fit. The budgets are
 * the `maxTokens` and `maxDepth` of `Html\InlineSanitizer`, `InlineSanitizer::DEFAULT_MAX_TOKENS` and
 * `InlineSanitizer::DEFAULT_MAX_DEPTH` unless configured; `$budget` says which one, `tokens` or `depth`, and `$limit`
 * its value.
 */
final class InputTooComplex extends InvalidArgumentException implements UjoException
{
    public function __construct(
        string $message,
        public readonly string $budget,
        public readonly int $limit,
    ) {
        parent::__construct($message);
    }

    public static function tokens(int $limit): self
    {
        return new self(\sprintf('The input holds more than %d tags and comments.', $limit), 'tokens', $limit);
    }

    public static function depth(int $limit): self
    {
        return new self(\sprintf('The input nests its elements deeper than %d levels.', $limit), 'depth', $limit);
    }
}
