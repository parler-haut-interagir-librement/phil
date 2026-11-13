<?php

declare(strict_types=1);

namespace Phil\FontsBundle\Application;

use Closure;
use Phil\Fonts\Brotli;
use Phil\Fonts\Exception\FontsException;
use Phil\Fonts\FontFile;
use Phil\FontsBundle\Application\Exception\FontUnreadable;

/**
 * Reads font files with the Brotli decompressor PHP has: WOFF2 files are read when `ext-brotli` is loaded, and every
 * other format everywhere. A file that cannot be read throws `FontUnreadable`, which names it.
 */
final readonly class FontFiles
{
    /**
     * @param (Closure(string, int): (string|false))|null $brotli the decompressor of WOFF2 files; none, WOFF2 is refused
     */
    public function __construct(
        private ?Closure $brotli = null,
    ) {
    }

    /**
     * The reader of this PHP: with `brotli_uncompress()` when `ext-brotli` is loaded.
     */
    public static function withAvailableBrotli(): self
    {
        return new self(Brotli::decompressor());
    }

    /**
     * Whether WOFF2 files are read.
     */
    public function readsWoff2(): bool
    {
        return $this->brotli instanceof Closure;
    }

    public function read(string $path): FontFile
    {
        try {
            return FontFile::fromFile($path, $this->brotli);
        } catch (FontsException $fontsException) {
            throw FontUnreadable::file(basename($path), $fontsException);
        }
    }

    /**
     * @param string $name the file's name, for the message of a file that cannot be read
     */
    public function readBytes(string $bytes, string $name): FontFile
    {
        try {
            return FontFile::fromBytes($bytes, $this->brotli);
        } catch (FontsException $fontsException) {
            throw FontUnreadable::file($name, $fontsException);
        }
    }
}
