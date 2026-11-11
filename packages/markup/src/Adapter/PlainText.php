<?php

declare(strict_types=1);

namespace Phil\Markup\Adapter;

/**
 * Plain text as HTML: a paragraph for each block separated by a blank line, a line break for each other new line,
 * every character escaped, and the web addresses made links.
 */
final class PlainText
{
    public static function compile(string $source): string
    {
        $paragraphs = [[]];

        foreach (explode("\n", $source) as $line) {
            if ('' !== trim($line)) {
                $paragraphs[\count($paragraphs) - 1][] = Autolink::html(trim($line));
            } elseif ([] !== $paragraphs[\count($paragraphs) - 1]) {
                $paragraphs[] = [];
            }
        }

        return implode("\n", array_map(static fn (array $lines): string => '<p>'.implode("<br>\n", $lines).'</p>', array_filter($paragraphs)));
    }
}
