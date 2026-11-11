<?php

declare(strict_types=1);

namespace Phil\Markup\Exception;

use InvalidArgumentException;
use Throwable;

/**
 * A source that cannot be read in its format: UJO JSON that is not JSON, or whose blocks break their types. The
 * readers of the formats people write — Markdown, BBCode, the wikis — never throw it: any text is a document.
 */
final class InvalidSource extends InvalidArgumentException implements MarkupException
{
    public static function because(string $format, string $reason, ?Throwable $previous = null): self
    {
        return new self(\sprintf('The %s source cannot be read: %s', $format, $reason), previous: $previous);
    }
}
