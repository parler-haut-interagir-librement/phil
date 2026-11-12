<?php

declare(strict_types=1);

namespace Phil\SocialCards\Card;

use Phil\Fonts\Exception\FontsException;
use Phil\Fonts\FontFile;
use Phil\Fonts\Format;
use Phil\Fonts\Licence\Licence;
use Phil\SocialCards\Exception\InvalidCardTemplate;

/**
 * A font a card is drawn in: a TrueType or OpenType file on disk, read by phil/fonts for its family, its licence and
 * its SHA-256 — the hash, not the path, enters the fingerprint of a card, so that the same font installed elsewhere draws
 * the same card.
 *
 * WOFF, WOFF2 and collections are refused: the image libraries draw from a plain `sfnt` file.
 */
final readonly class CardFont
{
    /** The largest font file read. */
    public const int MAX_BYTES = 32 * 1024 * 1024;

    private function __construct(
        public string $path,
        public string $sha256,
        public string $family,
        public Licence $licence,
    ) {
    }

    public static function fromFile(string $path): self
    {
        $size = is_file($path) && is_readable($path) ? filesize($path) : false;
        $bytes = false === $size || $size > self::MAX_BYTES ? false : file_get_contents($path);

        if (false === $bytes) {
            throw InvalidCardTemplate::because(\sprintf('The font "%s" cannot be read, or is larger than %d bytes.', $path, self::MAX_BYTES));
        }

        try {
            $file = FontFile::fromBytes($bytes);
        } catch (FontsException $fontsException) {
            throw InvalidCardTemplate::because(\sprintf('The font "%s" is not a font: %s', $path, $fontsException->getMessage()), $fontsException);
        }

        if (!\in_array($file->format, [Format::TrueType, Format::OpenType], true)) {
            throw InvalidCardTemplate::because(\sprintf('The font "%s" is a %s file; a card is drawn from a TrueType or OpenType file.', $path, $file->format->value));
        }

        $face = $file->face();

        return new self($path, $file->sha256, $face->family(), $face->licence());
    }
}
