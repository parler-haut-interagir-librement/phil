<?php

declare(strict_types=1);

namespace Phil\Migration;

/**
 * Lines cut into messages no longer than a transport takes: a title on the first, another on those that follow, the
 * lines kept whole when they fit and cut at a space when one alone is too long.
 *
 * @internal
 */
final class Messages
{
    /**
     * @param list<string> $lines
     *
     * @return list<string>
     */
    public static function split(array $lines, string $title, string $continued, int $maxLength): array
    {
        if ([] === $lines) {
            return [];
        }

        $parts = 1;

        do {
            $assumed = $parts;
            $bodies = self::bodies($lines, $maxLength - self::headerWidth($title, $continued, $assumed));
            $parts = \count($bodies);
        } while (self::headerWidth($title, $continued, $parts) > self::headerWidth($title, $continued, $assumed));

        $messages = [];

        foreach ($bodies as $index => $body) {
            $header = self::header(0 === $index ? $title : $continued, $index + 1, $parts);
            $messages[] = '' === $header ? $body : $header."\n\n".$body;
        }

        return $messages;
    }

    private static function header(string $template, int $part, int $parts): string
    {
        return str_replace('{counter}', $parts > 1 ? \sprintf(' (%d/%d)', $part, $parts) : '', $template);
    }

    /**
     * The room the widest title takes when a release takes this many messages, the blank line after it included.
     */
    private static function headerWidth(string $title, string $continued, int $parts): int
    {
        $width = 0;

        foreach ([$title, $continued] as $template) {
            if ('' !== $template) {
                $width = max($width, mb_strlen(self::header($template, $parts, $parts)) + 2);
            }
        }

        return $width;
    }

    /**
     * @param non-empty-list<string> $lines
     *
     * @return non-empty-list<string>
     */
    private static function bodies(array $lines, int $room): array
    {
        $bodies = [];
        $current = [];
        // The length of the message being filled, its line breaks included.
        $length = 0;

        foreach ($lines as $line) {
            foreach (self::pieces($line, $room) as $piece) {
                $pieceLength = mb_strlen($piece);

                if ([] !== $current && $length + 1 + $pieceLength > $room) {
                    $bodies[] = implode("\n", $current);
                    $current = [];
                }

                $length = [] === $current ? $pieceLength : $length + 1 + $pieceLength;
                $current[] = $piece;
            }
        }

        $bodies[] = implode("\n", $current);

        return $bodies;
    }

    /**
     * A line cut at the last space that fits, or in the middle of a word when none does.
     *
     * @return list<string>
     */
    private static function pieces(string $line, int $room): array
    {
        $pieces = [];

        while (mb_strlen($line) > $room) {
            $space = mb_strrpos(mb_substr($line, 0, $room), ' ', 1);
            $at = false === $space ? $room : $space;
            $pieces[] = rtrim(mb_substr($line, 0, $at));
            $line = ltrim(mb_substr($line, $at));
        }

        $pieces[] = $line;

        return $pieces;
    }
}
