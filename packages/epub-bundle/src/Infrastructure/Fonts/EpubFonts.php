<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Infrastructure\Fonts;

use Phil\Epub\Asset;
use Phil\Epub\Book;
use Phil\EpubBundle\Application\Exception\FontNotEmbeddable;
use Phil\Fonts\Css\FontFaceRule;
use Phil\Fonts\Font;
use Phil\Fonts\Format;
use Phil\FontsBundle\Application\CatalogueEntry;
use Phil\FontsBundle\Application\FontCatalogue;
use Phil\FontsBundle\Application\FontFaces;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Embeds families of the font directory of phil/fonts-bundle in a book: one file per face — the smallest of its formats,
 * WOFF2 first, which reading systems read even where PHP cannot —, obfuscated when configured, and a stylesheet of their
 * `@font-face` rules, linked from every content document. A face whose licence forbids embedding, or allows bitmaps
 * only, is refused before the book is written.
 */
final readonly class EpubFonts
{
    public function __construct(
        private FontCatalogue $catalogue,
        private bool $obfuscate = true,
        private string $directory = 'fonts',
        private string $stylesheet = 'css/fonts.css',
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * The book with the faces of each family given, and the stylesheet that declares them.
     */
    public function embed(Book $book, string ...$families): Book
    {
        $assets = [];
        $rules = [];
        $up = str_repeat('../', substr_count($this->stylesheet, '/'));

        foreach ($families as $family) {
            $faces = $this->faces($family);

            if ([] === $faces) {
                throw FontNotEmbeddable::because(\sprintf('The font directory holds no readable file of the family "%s".', $family));
            }

            foreach ($faces as [$font, $entry]) {
                $name = $font->fullName() ?? $family;

                if (!$font->embedding->usage->allowsEmbedding()) {
                    throw FontNotEmbeddable::because(\sprintf('The licence of the font "%s" forbids embedding it in a book.', $name));
                }

                if ($font->embedding->bitmapOnly()) {
                    throw FontNotEmbeddable::because(\sprintf('The font "%s" may only be embedded as bitmaps, which a book cannot carry.', $name));
                }

                $path = $this->directory.'/'.(preg_replace('{[^A-Za-z0-9._/-]+}', '-', $entry->path) ?? $entry->path);
                $assets[] = Asset::font($path, $this->filesystem->readFile($entry->absolutePath), $this->obfuscate);
                $rules[] = FontFaceRule::for($font)->withoutLocal()->withUrl($up.$path, $entry->format)->toCss();
            }
        }

        return $book->withAsset(...[...$assets, Asset::stylesheet($this->stylesheet, implode("\n\n", $rules)."\n")]);
    }

    /**
     * The faces of a family, each with the file a book carries: by stem, the smallest format, its metadata from the
     * first of its files that was read.
     *
     * @return list<array{Font, CatalogueEntry}>
     */
    private function faces(string $family): array
    {
        $stems = [];

        foreach ($this->catalogue->entries() as $entry) {
            if (Format::Collection !== $entry->format) {
                $stems[$entry->stem()][] = $entry;
            }
        }

        $faces = [];

        foreach ($stems as $entries) {
            usort($entries, static fn (CatalogueEntry $one, CatalogueEntry $other): int => array_search($one->format, FontFaces::ORDER, true) <=> array_search($other->format, FontFaces::ORDER, true));
            $file = array_find($entries, static fn (CatalogueEntry $entry): bool => $entry->isReadable())?->file;
            $font = $file?->face();

            if ($font instanceof Font && 0 === strcasecmp($font->family(), $family)) {
                $faces[] = [$font, $entries[0]];
            }
        }

        return $faces;
    }
}
