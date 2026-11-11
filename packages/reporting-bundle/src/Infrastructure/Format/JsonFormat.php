<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\Format;

use Override;
use Phil\ReportingBundle\Application\Contract\ReportFormat;
use Phil\ReportingBundle\Application\Report\ReportStream;

/**
 * A JSON array with one object per row, keyed by the columns' headings, written row by row.
 */
final readonly class JsonFormat implements ReportFormat
{
    private const int FLAGS = \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION;

    #[Override]
    public static function key(): string
    {
        return 'json';
    }

    #[Override]
    public function mediaType(): string
    {
        return 'application/json';
    }

    #[Override]
    public function extension(): string
    {
        return 'json';
    }

    #[Override]
    public function write(ReportStream $stream, array $columns, iterable $rows): void
    {
        $separator = "[\n";

        foreach ($rows as $row) {
            $stream->write($separator.json_encode((object) array_combine($columns, $row), self::FLAGS));
            $separator = ",\n";
        }

        $stream->write("[\n" === $separator ? "[]\n" : "\n]\n");
    }
}
