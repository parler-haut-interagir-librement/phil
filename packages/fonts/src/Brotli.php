<?php

declare(strict_types=1);

namespace Phil\Fonts;

use Closure;

/**
 * The Brotli decompressor WOFF2 files need, when PHP has one: `brotli_uncompress()` from `ext-brotli`.
 *
 * ```php
 * $file = FontFile::fromFile($path, Brotli::decompressor());
 * ```
 *
 * Without the extension it gives null, and the reading methods then read TrueType, OpenType, collections and WOFF and
 * refuse a WOFF2 file with `UnsupportedFont`, whose message says what to do.
 */
final class Brotli
{
    /** The function of `ext-brotli` that decompresses a Brotli stream. */
    public const string FUNCTION = 'brotli_uncompress';

    private function __construct()
    {
    }

    /**
     * The decompressor, or null when the function is not defined.
     *
     * @param string $function the function that takes the compressed bytes and the expected length and returns the
     *                         bytes, anything else counting as `false`; `brotli_uncompress` unless another decoder is named
     *
     * @return (Closure(string, int): (string|false))|null
     */
    public static function decompressor(string $function = self::FUNCTION): ?Closure
    {
        if (!\function_exists($function)) {
            return null;
        }

        return static function (string $compressed, int $length) use ($function): string|false {
            $bytes = \call_user_func($function, $compressed, $length);

            return \is_string($bytes) ? $bytes : false;
        };
    }
}
