<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Domain\Anchoring;

/**
 * The text of a field of inline HTML as a browser reads it into `textContent` — tags left out, character references
 * decoded —, and a passage of that text replaced while every tag stays where it was, so that the marks around and
 * inside the passage remain balanced.
 */
final class InlineHtml
{
    private const string UNITS = '/<[^>]*>|&(?:#[0-9]{1,7}|#[xX][0-9a-fA-F]{1,6}|[A-Za-z][A-Za-z0-9]{1,31});|./su';

    public static function text(string $html): string
    {
        return implode('', array_column(self::units($html), 1));
    }

    /**
     * The HTML with the characters from `$start` to `$start + $length` of its text replaced by `$replacement`,
     * escaped, written where the first of them was. The tags between them are kept, empty if need be.
     */
    public static function replace(string $html, int $start, int $length, string $replacement): string
    {
        $out = '';
        $index = 0;
        $written = false;

        foreach (self::units($html) as [$unit, $text]) {
            if (null === $text || $index < $start || $index >= $start + $length) {
                $out .= $unit;
                $index += mb_strlen($text ?? '');

                continue;
            }

            if (!$written) {
                $out .= htmlspecialchars($replacement, \ENT_QUOTES | \ENT_HTML5 | \ENT_SUBSTITUTE);
                $written = true;
            }

            $index += mb_strlen($text);
        }

        return $out;
    }

    /**
     * The HTML cut into tags, whose text is `null`, character references and single characters; nothing when it is
     * not UTF-8.
     *
     * @return list<array{0: string, 1: ?string}>
     */
    private static function units(string $html): array
    {
        preg_match_all(self::UNITS, $html, $matches);

        return array_map(static fn (string $unit): array => match ($unit[0]) {
            '<' => [$unit, 1 === \strlen($unit) ? $unit : null],
            '&' => [$unit, html_entity_decode($unit, \ENT_QUOTES | \ENT_HTML5, 'UTF-8')],
            default => [$unit, $unit],
        }, $matches[0]);
    }
}
