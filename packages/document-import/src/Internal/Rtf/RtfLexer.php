<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Rtf;

use Generator;

/**
 * The tokens of an RTF file, in order: group openings and closings, control words with their parameter, control
 * symbols, bytes written as `\'hh`, binary data after `\binN`, and runs of text. Line breaks of the file are not text.
 *
 * @internal
 */
final class RtfLexer
{
    public const int OPEN = 1;

    public const int CLOSE = 2;

    public const int WORD = 3;

    public const int SYMBOL = 4;

    public const int BYTE = 5;

    public const int TEXT = 6;

    public const int BINARY = 7;

    /**
     * @param int $binaryBytes the most bytes `\binN` may announce
     *
     * @return Generator<int, array{int, string, ?int}>
     */
    public static function tokens(string $rtf, int $binaryBytes): Generator
    {
        $length = \strlen($rtf);
        $at = 0;

        while ($at < $length) {
            $char = $rtf[$at];

            if ('{' === $char || '}' === $char) {
                ++$at;
                yield ['{' === $char ? self::OPEN : self::CLOSE, $char, null];

                continue;
            }

            if ("\r" === $char || "\n" === $char) {
                ++$at;

                continue;
            }

            if ('\\' !== $char) {
                $run = strcspn($rtf, "\\{}\r\n", $at);
                yield [self::TEXT, substr($rtf, $at, $run), null];
                $at += $run;

                continue;
            }

            [$token, $at] = self::control($rtf, $at + 1, $binaryBytes);

            if (null !== $token) {
                yield $token;
            }
        }
    }

    /**
     * The control word or symbol after a backslash, and the position after it.
     *
     * @return array{array{int, string, ?int}|null, int}
     */
    private static function control(string $rtf, int $at, int $binaryBytes): array
    {
        if (1 === preg_match('/\G([a-zA-Z]{1,32})(-?\d{1,10})? ?/', $rtf, $match, 0, $at)) {
            $at += \strlen($match[0]);
            $parameter = isset($match[2]) ? (int) $match[2] : null;

            if ('bin' === $match[1]) {
                $length = $parameter ?? 0;
                $bytes = max(0, min($length, $binaryBytes));

                return [[self::BINARY, substr($rtf, $at, $bytes), null], $at + $bytes];
            }

            return [[self::WORD, $match[1], $parameter], $at];
        }

        if (1 === preg_match('/\G\'([0-9a-fA-F]{2})/', $rtf, $match, 0, $at)) {
            return [[self::BYTE, \chr((int) hexdec($match[1]) & 0xFF), null], $at + 3];
        }

        $symbol = $rtf[$at] ?? '';

        return match ($symbol) {
            '' => [null, $at],
            '\\', '{', '}' => [[self::TEXT, $symbol, null], $at + 1],
            "\r", "\n" => [[self::WORD, 'par', null], $at + 1],
            default => [[self::SYMBOL, $symbol, null], $at + 1],
        };
    }
}
