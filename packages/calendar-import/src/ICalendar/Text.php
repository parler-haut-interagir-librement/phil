<?php

declare(strict_types=1);

namespace Phil\CalendarImport\ICalendar;

/**
 * The values of type TEXT (RFC 5545, section 3.3.11), read as plain text: `\n` and `\N` are line breaks, `\\`, `\;` and
 * `\,` the characters they escape; another backslash stays as written. Nothing is read as HTML, and nothing is decoded
 * further: what comes out is text, to be escaped where it is shown.
 *
 * @internal
 */
final class Text
{
    /**
     * A value as plain text, its spaces at both ends taken away; null when nothing is left.
     */
    public static function of(string $value): ?string
    {
        $text = trim(self::unescape($value));

        return '' === $text ? null : $text;
    }

    /**
     * A list of texts separated by commas that are not escaped, each trimmed, the empty ones left out, each kept once.
     *
     * @return list<string>
     */
    public static function list(string $value): array
    {
        preg_match_all('/(?:\\\\.|[^\\\\,])++(?:\\\\$)?|\\\\$/sD', $value, $items);
        $texts = [];

        foreach ($items[0] as $item) {
            $text = self::of($item);

            if (null !== $text && !\in_array($text, $texts, true)) {
                $texts[] = $text;
            }
        }

        return $texts;
    }

    private static function unescape(string $value): string
    {
        return (string) preg_replace_callback('/\\\\(.)/s', static fn (array $escape): string => match ($escape[1]) {
            'n', 'N' => "\n",
            '\\', ';', ',' => $escape[1],
            default => $escape[0],
        }, $value);
    }
}
