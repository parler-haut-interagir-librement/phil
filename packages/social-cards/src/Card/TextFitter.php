<?php

declare(strict_types=1);

namespace Phil\SocialCards\Card;

use Phil\SocialCards\Contract\TextMeasure;
use Phil\SocialCards\Text;

/**
 * Fits a text to a box: the largest size between two bounds at which it holds in so many lines of so many pixels, found
 * by halving the range; at the smallest size, the lines that hold, the last one ending in an ellipsis. A word wider than
 * the box is cut between two characters.
 *
 * Each size measures every piece of the text once, and the halving tries a handful of sizes: a title of 300 characters
 * costs a few hundred measures.
 */
final readonly class TextFitter
{
    public function __construct(
        private TextMeasure $measure,
    ) {
    }

    public function fit(string $text, string $locale, CardFont $font, int $width, int $maxLines, int $maxSize, int $minSize): FittedText
    {
        if ($maxLines < 1) {
            return new FittedText([], $maxSize, true);
        }

        $segments = LineBreaker::segments($text, $locale);
        $lines = $this->wrap($segments, $font, $maxSize, $width, $maxLines);

        if (\count($lines) <= $maxLines) {
            return new FittedText($lines, $maxSize);
        }

        $smallest = $this->wrap($segments, $font, $minSize, $width, $maxLines);

        if (\count($smallest) > $maxLines) {
            return $this->truncated($smallest, $font, $minSize, $width, $maxLines);
        }

        // The largest size that holds lies in [low, high): `low` holds and `high` does not.
        [$low, $high, $fitting] = [$minSize, $maxSize, $smallest];

        while ($high - $low > 1) {
            $middle = intdiv($low + $high, 2);
            $lines = $this->wrap($segments, $font, $middle, $width, $maxLines);

            if (\count($lines) <= $maxLines) {
                [$low, $fitting] = [$middle, $lines];
            } else {
                $high = $middle;
            }
        }

        return new FittedText($fitting, $low);
    }

    /**
     * The lines of the pieces at a size, at most one more than `$maxLines`: enough to know the text does not hold.
     *
     * @param list<string> $segments
     *
     * @return list<string>
     */
    private function wrap(array $segments, CardFont $font, int $size, int $width, int $maxLines): array
    {
        $lines = [];
        $line = '';

        foreach ($segments as $segment) {
            if ($this->holds($line.$segment, $font, $size, $width)) {
                $line .= $segment;

                continue;
            }

            if ('' !== $line) {
                $lines[] = rtrim($line);
            }

            $line = $segment;

            if (!$this->holds($segment, $font, $size, $width)) {
                $line = '';

                foreach ($this->graphemes($segment) as $grapheme) {
                    if ('' !== $line && !$this->holds($line.$grapheme, $font, $size, $width)) {
                        $lines[] = $line;
                        $line = '';
                    }

                    $line .= $grapheme;
                }
            }

            if (\count($lines) > $maxLines) {
                return $lines;
            }
        }

        if ('' !== $line) {
            $lines[] = rtrim($line);
        }

        return $lines;
    }

    /**
     * The characters of a text as a reader sees them: a letter and its accents, an emoji and its modifiers, are one.
     *
     * @return list<string>
     */
    private function graphemes(string $text): array
    {
        preg_match_all('/\X/u', $text, $graphemes);

        return $graphemes[0];
    }

    private function holds(string $text, CardFont $font, int $size, int $width): bool
    {
        return $this->measure->width(rtrim($text), $font, $size) <= $width;
    }

    /**
     * The first lines, the last one shortened until it holds with an ellipsis.
     *
     * @param list<string> $lines
     */
    private function truncated(array $lines, CardFont $font, int $size, int $width, int $maxLines): FittedText
    {
        $kept = \array_slice($lines, 0, $maxLines);
        $last = $this->graphemes((string) array_pop($kept));

        while ([] !== $last && !$this->holds(implode('', $last).Text::ELLIPSIS, $font, $size, $width)) {
            array_pop($last);
        }

        $kept[] = rtrim(implode('', $last)).Text::ELLIPSIS;

        return new FittedText($kept, $size, true);
    }
}
