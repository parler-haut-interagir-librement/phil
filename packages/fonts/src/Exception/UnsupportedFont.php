<?php

declare(strict_types=1);

namespace Phil\Fonts\Exception;

use RuntimeException;

/**
 * A valid font file the library does not read: a WOFF2 file without a Brotli decompressor, a WOFF2 collection.
 */
final class UnsupportedFont extends RuntimeException implements FontsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    /**
     * A WOFF2 file read without a Brotli decompressor; the message says what to do instead.
     */
    public static function woff2WithoutBrotli(): self
    {
        return new self('A WOFF2 file compresses its tables with Brotli, which PHP does not decode without ext-brotli: install ext-brotli and pass Brotli::decompressor(), or read the WOFF, TrueType or OpenType file of the font.');
    }
}
