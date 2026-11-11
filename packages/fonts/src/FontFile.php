<?php

declare(strict_types=1);

namespace Phil\Fonts;

use Closure;
use Phil\Fonts\Exception\InvalidFont;
use Phil\Fonts\Reader\FontReader;

/**
 * A font file read for its metadata: its format, its faces — one, or several for a collection — its SHA-256 and, for
 * WOFF and WOFF2, the extended metadata XML it may carry.
 *
 * WOFF2 compresses its tables with Brotli, which PHP does not decode on its own: pass a decompressor, a closure that
 * takes the compressed bytes and the expected length and returns the bytes: `Brotli::decompressor()` gives
 * `brotli_uncompress()` when `ext-brotli` is loaded. Without one, a WOFF2 file throws `UnsupportedFont`.
 */
final readonly class FontFile
{
    /**
     * @param non-empty-list<Font> $faces
     */
    public function __construct(
        public Format $format,
        public array $faces,
        public string $sha256,
        public ?string $extendedMetadata = null,
    ) {
    }

    /**
     * @param (Closure(string, int): (string|false))|null $brotli
     */
    public static function fromBytes(string $bytes, ?Closure $brotli = null): self
    {
        [$format, $faces, $metadata] = FontReader::read($bytes, $brotli);

        return new self($format, $faces, hash('sha256', $bytes), $metadata);
    }

    /**
     * @param (Closure(string, int): (string|false))|null $brotli
     */
    public static function fromFile(string $path, ?Closure $brotli = null): self
    {
        $bytes = is_file($path) && is_readable($path) ? file_get_contents($path) : false;

        if (false === $bytes) {
            throw InvalidFont::because(\sprintf('The font file "%s" cannot be read.', $path));
        }

        return self::fromBytes($bytes, $brotli);
    }

    /**
     * A face by its index in the file, the only one of a file that is not a collection.
     */
    public function face(int $index = 0): Font
    {
        return $this->faces[$index] ?? throw InvalidFont::because(\sprintf('The font file has no face %d; it has %d.', $index, \count($this->faces)));
    }
}
