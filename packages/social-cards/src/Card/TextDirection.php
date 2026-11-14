<?php

declare(strict_types=1);

namespace Phil\SocialCards\Card;

use IntlChar;

/**
 * The base direction of a text, as the Unicode bidirectional algorithm finds it: that of its first letter of a strong
 * direction — Latin, Greek or Cyrillic from left to right, Hebrew or Arabic from right to left —, from left to right
 * when it has none. A renderer that lays out the text aligns the lines of a text from right to left on the right edge.
 */
enum TextDirection
{
    case LeftToRight;
    case RightToLeft;

    /** The characters read to find the first letter of a strong direction. */
    public const int MAX_SCAN = 1024;

    public static function of(string $text): self
    {
        foreach (mb_str_split(mb_substr($text, 0, self::MAX_SCAN, 'UTF-8'), 1, 'UTF-8') as $character) {
            $direction = IntlChar::charDirection($character);

            if (IntlChar::CHAR_DIRECTION_LEFT_TO_RIGHT === $direction) {
                return self::LeftToRight;
            }

            if (IntlChar::CHAR_DIRECTION_RIGHT_TO_LEFT === $direction || IntlChar::CHAR_DIRECTION_RIGHT_TO_LEFT_ARABIC === $direction) {
                return self::RightToLeft;
            }
        }

        return self::LeftToRight;
    }
}
