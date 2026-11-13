<?php

declare(strict_types=1);

namespace Phil\Citations\Internal;

use Phil\Citations\Exception\InvalidReference;
use Phil\Citations\Model\Name;

/**
 * The names of a BibTeX field — `author`, `editor` — split on `and` and read in BibTeX's three forms: « First von
 * Last », « von Last, First » and « von Last, Jr, First ». A name in one pair of braces is a literal, `others` is left
 * out, a name without a family is left out, and the von part becomes a non-dropping particle, as citation managers
 * read it.
 *
 * @internal
 */
final class BibTeXNames
{
    /**
     * @return list<Name>
     */
    public static function parse(string $raw, int $maxNames): array
    {
        $names = [];

        foreach (self::split($raw) as $tokens) {
            if (\count($names) >= $maxNames) {
                break;
            }

            try {
                $name = self::name($tokens);
            } catch (InvalidReference) {
                continue;
            }

            if ($name instanceof Name) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * The tokens of each name: its words, and its commas as `,`, at brace depth 0.
     *
     * @return list<list<string>>
     */
    private static function split(string $raw): array
    {
        $names = [];
        $tokens = [];
        $word = '';
        $depth = 0;
        $length = \strlen($raw);

        for ($position = 0; $position < $length; ++$position) {
            $char = $raw[$position];
            $depth += match ($char) {
                '{' => 1,
                '}' => $depth > 0 ? -1 : 0,
                default => 0,
            };

            if (0 === $depth && (ctype_space($char) || ',' === $char)) {
                if ('' !== $word) {
                    $tokens[] = $word;
                    $word = '';
                }

                if (',' === $char) {
                    $tokens[] = ',';
                }

                continue;
            }

            $word .= $char;
        }

        if ('' !== $word) {
            $tokens[] = $word;
        }

        $current = [];

        foreach ($tokens as $token) {
            if (0 === strcasecmp($token, 'and')) {
                $names[] = $current;
                $current = [];

                continue;
            }

            $current[] = $token;
        }

        $names[] = $current;

        return $names;
    }

    /**
     * @param list<string> $tokens
     */
    private static function name(array $tokens): ?Name
    {
        $parts = [[]];

        foreach ($tokens as $token) {
            if (',' === $token) {
                $parts[] = [];
            } else {
                $parts[\count($parts) - 1][] = $token;
            }
        }

        if ([] === $parts[0] || (1 === \count($parts) && ['others'] === $parts[0])) {
            return null;
        }

        if (1 === \count($parts)) {
            return self::natural($parts[0]);
        }

        [$von, $last] = self::vonLast($parts[0]);
        $suffix = 3 <= \count($parts) ? self::text($parts[1]) : null;
        $given = self::text($parts[\count($parts) - 1]);

        return new Name($last, $given, null, $von, $suffix);
    }

    /**
     * @param non-empty-list<string> $words
     */
    private static function natural(array $words): ?Name
    {
        if (1 === \count($words) && str_starts_with($words[0], '{') && str_ends_with($words[0], '}')) {
            $literal = self::text($words);

            return null === $literal ? null : Name::literal($literal);
        }

        $last = \count($words) - 1;
        $firstLower = null;
        $lastLower = null;

        for ($index = 0; $index < $last; ++$index) {
            if (self::isLowercase($words[$index])) {
                $firstLower ??= $index;
                $lastLower = $index;
            }
        }

        if (null === $firstLower || null === $lastLower) {
            return new Name(self::text([$words[$last]]), self::text(\array_slice($words, 0, $last)));
        }

        return new Name(
            self::text(\array_slice($words, $lastLower + 1)),
            self::text(\array_slice($words, 0, $firstLower)),
            null,
            self::text(\array_slice($words, $firstLower, $lastLower - $firstLower + 1)),
        );
    }

    /**
     * @param non-empty-list<string> $words
     *
     * @return array{?string, ?string}
     */
    private static function vonLast(array $words): array
    {
        $lastLower = null;

        for ($index = 0; $index < \count($words) - 1; ++$index) {
            if (self::isLowercase($words[$index])) {
                $lastLower = $index;
            }
        }

        if (null === $lastLower) {
            return [null, self::text($words)];
        }

        return [self::text(\array_slice($words, 0, $lastLower + 1)), self::text(\array_slice($words, $lastLower + 1))];
    }

    private static function isLowercase(string $word): bool
    {
        if (str_starts_with($word, '{')) {
            return false;
        }

        $letter = mb_substr(LatexText::plain($word), 0, 1);

        return '' !== $letter
            && mb_strtolower($letter) === $letter
            && mb_strtoupper($letter) !== $letter;
    }

    /**
     * @param list<string> $words
     */
    private static function text(array $words): ?string
    {
        $text = LatexText::plain(implode(' ', $words));

        return '' === $text ? null : $text;
    }
}
