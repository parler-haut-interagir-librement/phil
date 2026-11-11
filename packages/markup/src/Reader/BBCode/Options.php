<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\BBCode;

use Phil\Markup\Lexer\Cursor;

/**
 * What follows the name of an opening tag: an option after `=` — in quotes, or up to the first ` key=` —, then
 * attributes `key=value`, their value in quotes when it holds spaces, and flags, single words.
 *
 * @internal
 */
final class Options
{
    private const string KEY = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-';

    /**
     * @return array{?string, array<string, string>, list<string>}
     */
    public static function parse(string $rest): array
    {
        if (!str_starts_with($rest, '=')) {
            return [null, ...self::attributes($rest)];
        }

        $value = substr($rest, 1);
        $quote = $value[0] ?? '';
        $end = '"' === $quote || "'" === $quote ? strpos($value, $quote, 1) : false;

        if (false !== $end) {
            return [trim(substr($value, 1, $end - 1)), ...self::attributes(substr($value, $end + 1))];
        }

        $split = self::split($value);

        return [trim(substr($value, 0, $split)), ...self::attributes(substr($value, $split))];
    }

    /**
     * Where an option written without quotes ends: at the first space followed by a key and `=`.
     */
    private static function split(string $value): int
    {
        for ($at = strpos($value, ' '); false !== $at; $at = strpos($value, ' ', $at + 1)) {
            $key = strspn($value, self::KEY, $at + 1);

            if ($key > 0 && '=' === ($value[$at + 1 + $key] ?? '')) {
                return $at;
            }
        }

        return \strlen($value);
    }

    /**
     * @return array{array<string, string>, list<string>}
     */
    private static function attributes(string $text): array
    {
        $cursor = new Cursor($text);
        $attributes = [];
        $flags = [];

        for ($cursor->consumeWhile(' '); !$cursor->isEnd(); $cursor->consumeWhile(' ')) {
            $key = $cursor->consumeUntil(' =');

            if (!$cursor->consume('=')) {
                $flags[] = $key;

                continue;
            }

            $quote = $cursor->current();
            $quoted = ('"' === $quote || "'" === $quote) && $cursor->consume($quote);
            $attributes[strtolower($key)] = $quoted ? $cursor->consumeThrough($quote) ?? $cursor->rest() : $cursor->consumeUntil(' ');
        }

        return [$attributes, $flags];
    }
}
