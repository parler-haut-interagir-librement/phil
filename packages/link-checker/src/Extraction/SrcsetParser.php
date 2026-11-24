<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Extraction;

/**
 * The addresses of a `srcset` attribute, read as the HTML Standard reads its candidates: separated by commas, each an
 * address followed by an optional descriptor (`2x`, `640w`). An address may hold commas inside it; the commas that
 * close it are dropped. A descriptor may hold commas inside parentheses.
 *
 * One pass over the value, with no backtracking: linear in its length.
 *
 * @internal
 */
final class SrcsetParser
{
    private const string SPACES = " \t\n\r\f";

    private const string SPACES_AND_COMMAS = " \t\n\r\f,";

    /**
     * @return list<string>
     */
    public static function urls(string $srcset, int $max): array
    {
        $urls = [];
        $length = \strlen($srcset);
        $position = 0;

        while (\count($urls) < $max) {
            $position += strspn($srcset, self::SPACES_AND_COMMAS, $position);

            if ($position >= $length) {
                break;
            }

            $size = strcspn($srcset, self::SPACES, $position);
            $url = substr($srcset, $position, $size);
            $position += $size;
            $trimmed = rtrim($url, ',');

            // An address that ends with a comma has no descriptor: the next candidate starts after it.
            if ($trimmed === $url) {
                $position = self::afterDescriptor($srcset, $position, $length);
            }

            // The address starts after the spaces and commas skipped: it is never empty, even trimmed.
            $urls[] = $trimmed;
        }

        return $urls;
    }

    /**
     * The position of the comma that ends the descriptor that starts here, which the next candidate skips, or the end.
     */
    private static function afterDescriptor(string $srcset, int $position, int $length): int
    {
        $depth = 0;

        for (; $position < $length; ++$position) {
            $character = $srcset[$position];

            if ('(' === $character) {
                ++$depth;
            } elseif (')' === $character && $depth > 0) {
                --$depth;
            } elseif (',' === $character && 0 === $depth) {
                return $position;
            }
        }

        return $length;
    }
}
