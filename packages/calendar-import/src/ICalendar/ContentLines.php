<?php

declare(strict_types=1);

namespace Phil\CalendarImport\ICalendar;

use Phil\CalendarImport\Exception\InvalidCalendar;
use Phil\CalendarImport\Exception\LimitExceeded;
use Phil\CalendarImport\Exception\UnreadableCalendar;

/**
 * The lines of an iCalendar text (RFC 5545, section 3.1): unfolded — a line break followed by a space or a tab joins
 * the next line to it —, cut at each line break, CRLF or a lone LF, each read as a name, parameters and a value.
 *
 * The text is checked as UTF-8 once unfolded, so that a character folded in the middle of its bytes is read whole.
 * Every pattern is possessive: the time spent on a line grows with its length, never faster.
 *
 * @internal
 */
final class ContentLines
{
    /** A parameter value: quoted, without a quote or a control character, or bare, without `"`, `;`, `:`, `,` either. */
    private const string VALUE = '(?:"[^"\x00-\x08\x0A-\x1F\x7F]*+"|[^";:,\x00-\x08\x0A-\x1F\x7F]*+)';

    private const string LINE = '/^([A-Za-z0-9-]++)((?:;[A-Za-z0-9-]++='.self::VALUE.'(?:,'.self::VALUE.')*+)*+):([^\x00-\x08\x0A-\x1F\x7F]*+)$/D';

    private const string PARAMETER = '/\G;([A-Za-z0-9-]++)=('.self::VALUE.'(?:,'.self::VALUE.')*+)/';

    private const string ITEM = '/\G,?('.self::VALUE.')/';

    /**
     * @return list<ContentLine>
     *
     * @throws UnreadableCalendar
     */
    public static function of(string $text, ReaderLimits $limits): array
    {
        $text = str_starts_with($text, "\u{FEFF}") ? substr($text, 3) : $text;
        $text = preg_replace('/(?:\r\n|\n|\r)[ \t]/', '', $text) ?? $text;

        if (1 !== preg_match('//u', $text)) {
            throw InvalidCalendar::because('A calendar is written in UTF-8.');
        }

        $lines = [];

        // A CRLF becomes two line breaks, and the blank line between them is skipped like any other.
        foreach (explode("\n", str_replace("\r", "\n", $text)) as $line) {
            if (\strlen($line) > $limits->lineBytes) {
                throw LimitExceeded::of(Limit::LineBytes, $limits->lineBytes);
            }

            if ('' !== $line) {
                $lines[] = self::line($line);
            }
        }

        return $lines;
    }

    public static function line(string $line): ContentLine
    {
        if (1 !== preg_match(self::LINE, $line, $match)) {
            return ContentLine::malformed(\sprintf('"%s" is not a name, parameters, a colon and a value.', self::excerpt($line)));
        }

        $parameters = [];

        for ($at = 0; 1 === preg_match(self::PARAMETER, $match[2], $found, 0, $at); $at += \strlen($found[0])) {
            $name = strtoupper($found[1]);

            if (isset($parameters[$name])) {
                return ContentLine::malformed(\sprintf('The parameter %s of %s appears twice.', $name, strtoupper($match[1])));
            }

            $parameters[$name] = self::values($found[2]);
        }

        return new ContentLine(strtoupper($match[1]), $parameters, $match[3]);
    }

    /**
     * The values of a parameter: unquoted, and decoded as RFC 6868 writes a line break (`^n`), a quote (`^'`) and a
     * caret (`^^`).
     *
     * @return list<string>
     */
    private static function values(string $text): array
    {
        $values = [];

        for ($at = 0, $length = \strlen($text); ($at < $length || [] === $values) && 1 === preg_match(self::ITEM, $text, $item, 0, $at); $at += \strlen($item[0])) {
            $value = str_starts_with($item[1], '"') ? substr($item[1], 1, -1) : $item[1];
            $values[] = preg_replace_callback('/\^([n^\'])/', static fn (array $caret): string => match ($caret[1]) {
                'n' => "\n",
                '^' => '^',
                default => '"',
            }, $value) ?? $value;
        }

        return $values;
    }

    private static function excerpt(string $line): string
    {
        $excerpt = mb_strcut($line, 0, 40);

        return preg_replace('/[\x00-\x1F\x7F]/', '?', $excerpt).($excerpt === $line ? '' : '…');
    }
}
