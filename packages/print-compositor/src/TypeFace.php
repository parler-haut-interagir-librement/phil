<?php

declare(strict_types=1);

namespace Phil\PrintCompositor;

use Phil\Fonts\Exception\FontsException;
use Phil\Fonts\Font;
use Phil\Fonts\FontFile;
use Phil\Fonts\Format;
use Phil\PrintCompositor\Exception\UnsupportedFont;

/**
 * A TrueType or OpenType font file that texts are drawn in, with what `phil/fonts` reads of it: its names, its
 * embedding permissions and the characters its character map covers.
 */
final readonly class TypeFace
{
    private function __construct(
        public string $path,
        public Font $font,
    ) {
    }

    /**
     * The face of a TrueType or OpenType file. WOFF, WOFF2 and collections are refused: FreeType, through GD, draws
     * from a plain font file.
     */
    public static function fromFile(string $path): self
    {
        try {
            $file = FontFile::fromFile($path);
        } catch (FontsException $fontsException) {
            throw UnsupportedFont::because($fontsException->getMessage(), $fontsException);
        }

        if (Format::TrueType !== $file->format && Format::OpenType !== $file->format) {
            throw UnsupportedFont::because(\sprintf('The font file "%s" is a %s file; texts are drawn from a TrueType or OpenType file.', $path, $file->format->value));
        }

        $resolved = realpath($path);
        \assert(false !== $resolved);

        return new self($resolved, $file->face());
    }

    /**
     * The characters of the text that the font does not cover, each once, in the order they first appear; line breaks
     * are not drawn and never count.
     *
     * @return list<string>
     */
    public function missingCharacters(string $text): array
    {
        $missing = [];

        foreach (mb_str_split(str_replace("\n", '', $text)) as $character) {
            $codePoint = mb_ord($character);

            if (!array_any($this->font->unicodeRanges, static fn (array $range): bool => $codePoint >= $range[0] && $codePoint <= $range[1])) {
                $missing[$character] = $character;
            }
        }

        return array_values($missing);
    }
}
