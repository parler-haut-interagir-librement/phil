<?php

declare(strict_types=1);

namespace Phil\Markup\Exception;

use InvalidArgumentException;

/**
 * A source that fits in its ceiling of bytes but holds more of something than its reader parses, refused whole before
 * it is parsed: a source is never cut to fit. The budget is the `maxLines` of `Reader\Markdown\MarkdownReader`,
 * `MarkdownReader::DEFAULT_MAX_LINES` unless configured; `$budget` says which one, `lines`, and `$limit` its value.
 */
final class InputTooComplex extends InvalidArgumentException implements MarkupException
{
    public function __construct(
        string $message,
        public readonly string $budget,
        public readonly int $limit,
    ) {
        parent::__construct($message);
    }

    public static function lines(int $limit): self
    {
        return new self(\sprintf('The input holds more than %d lines.', $limit), 'lines', $limit);
    }
}
