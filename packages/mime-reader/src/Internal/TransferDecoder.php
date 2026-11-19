<?php

declare(strict_types=1);

namespace Phil\MimeReader\Internal;

use Closure;

/**
 * A body with its transfer encoding undone (RFC 2045), chunk by chunk, so that an attachment written to a stream is
 * never held decoded in memory whole. Base64 drops what is not of its alphabet; quoted-printable keeps a malformed
 * escape as it is written; any other encoding gives the octets as they are.
 *
 * @internal
 */
final class TransferDecoder
{
    private const int CHUNK = 65_536;

    /**
     * @param Closure(string): void $sink receives each decoded chunk, never an empty one
     */
    public static function decode(string $source, int $offset, int $length, string $encoding, Closure $sink): void
    {
        match ($encoding) {
            'base64' => self::base64($source, $offset, $offset + $length, $sink),
            'quoted-printable' => self::quotedPrintable($source, $offset, $offset + $length, $sink),
            default => self::identity($source, $offset, $offset + $length, $sink),
        };
    }

    /**
     * @param Closure(string): void $sink
     */
    private static function identity(string $source, int $at, int $end, Closure $sink): void
    {
        for (; $at < $end; $at += self::CHUNK) {
            $sink(substr($source, $at, min(self::CHUNK, $end - $at)));
        }
    }

    /**
     * Groups of four characters are decoded as they come; a last group of two or three is decoded without its padding,
     * a last lone character dropped.
     *
     * @param Closure(string): void $sink
     */
    private static function base64(string $source, int $at, int $end, Closure $sink): void
    {
        $carry = '';

        for (; $at < $end; $at += self::CHUNK) {
            $chunk = $carry.preg_replace('/[^A-Za-z0-9+\/]+/', '', substr($source, $at, min(self::CHUNK, $end - $at)));
            $usable = \strlen($chunk) - \strlen($chunk) % 4;
            self::emit(Base64::groups(substr($chunk, 0, $usable), 0), $sink);
            $carry = substr($chunk, $usable);
        }

        self::emit(Base64::groups($carry, \strlen($carry)), $sink);
    }

    /**
     * Each chunk is cut after its last line break, or before an escape it would split, and the rest carried over.
     *
     * @param Closure(string): void $sink
     */
    private static function quotedPrintable(string $source, int $at, int $end, Closure $sink): void
    {
        $carry = '';

        for (; $at < $end; $at += self::CHUNK) {
            $data = $carry.substr($source, $at, min(self::CHUNK, $end - $at));
            $cut = strrpos($data, "\n");
            $cut = false === $cut ? \strlen($data) - self::escapeTail($data) : $cut + 1;
            self::emit(quoted_printable_decode(substr($data, 0, $cut)), $sink);
            $carry = substr($data, $cut);
        }

        self::emit(quoted_printable_decode($carry), $sink);
    }

    /**
     * How many of the last two characters belong to an escape the next chunk completes.
     */
    private static function escapeTail(string $data): int
    {
        $tail = substr($data, -2);
        $equals = strrpos($tail, '=');

        return false === $equals ? 0 : \strlen($tail) - $equals;
    }

    /**
     * @param Closure(string): void $sink
     */
    private static function emit(string $bytes, Closure $sink): void
    {
        if ('' !== $bytes) {
            $sink($bytes);
        }
    }
}
