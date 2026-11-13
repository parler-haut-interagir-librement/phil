<?php

declare(strict_types=1);

namespace Phil\FontsBundle\Application\Exception;

use Phil\Fonts\Exception\FontsException;
use RuntimeException;

/**
 * A font file the bundle could not read: the library's reason, prefixed with the file's name. A WOFF2 file read where
 * `ext-brotli` is not loaded says to install it, or to use the WOFF, TrueType or OpenType file of the font.
 */
final class FontUnreadable extends RuntimeException implements FontsException
{
    public static function file(string $name, FontsException $reason): self
    {
        return new self(\sprintf('The font file "%s" cannot be read: %s', $name, $reason->getMessage()), 0, $reason);
    }
}
