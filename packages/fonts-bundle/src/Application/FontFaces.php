<?php

declare(strict_types=1);

namespace Phil\FontsBundle\Application;

use Closure;
use Phil\Fonts\Css\FontDisplay;
use Phil\Fonts\Css\FontFaceRule;
use Phil\Fonts\Format;

/**
 * The `@font-face` rules of the catalogue. The files of one face in several formats — the same path but for the
 * extension, such as `sans/Regular.woff2` and `sans/Regular.ttf` — make one rule, their URLs smallest format first:
 * WOFF2, WOFF, OpenType, TrueType. The face's metadata comes from the first of them that was read, so a WOFF2 file that
 * cannot be read without `ext-brotli` is still served beside a sibling that can. Collections, faces without a family
 * and faces none of whose files was read are left out.
 */
final readonly class FontFaces
{
    /** The order of the sources of a rule. */
    public const array ORDER = [Format::Woff2, Format::Woff, Format::OpenType, Format::TrueType];

    /**
     * @param string $publicPath the URL path the directory is served at, such as `/fonts`
     */
    public function __construct(
        private FontCatalogue $catalogue,
        private string $publicPath = '/fonts',
        private FontDisplay $display = FontDisplay::Swap,
    ) {
    }

    /**
     * The rules, by stem.
     *
     * @param (Closure(CatalogueEntry): string)|null $url the URL of a file; under the public path when null
     *
     * @return list<FontFaceRule>
     */
    public function rules(?Closure $url = null): array
    {
        $url ??= $this->url(...);
        $groups = [];

        foreach ($this->catalogue->entries() as $entry) {
            if (Format::Collection !== $entry->format) {
                $groups[$entry->stem()][] = $entry;
            }
        }

        $rules = [];

        foreach ($groups as $entries) {
            usort($entries, static fn (CatalogueEntry $one, CatalogueEntry $other): int => array_search($one->format, self::ORDER, true) <=> array_search($other->format, self::ORDER, true));
            $file = array_find($entries, static fn (CatalogueEntry $entry): bool => $entry->isReadable())?->file;

            if (null === $file || '' === $file->face()->family()) {
                continue;
            }

            $rule = FontFaceRule::for($file->face())->withDisplay($this->display);

            foreach ($entries as $entry) {
                $rule = $rule->withUrl($url($entry), $entry->format);
            }

            $rules[] = $rule;
        }

        return $rules;
    }

    /**
     * The rules as one stylesheet, a blank line between two rules; empty when there is none.
     *
     * @param (Closure(CatalogueEntry): string)|null $url
     */
    public function css(?Closure $url = null): string
    {
        $rules = array_map(static fn (FontFaceRule $rule): string => $rule->toCss(), $this->rules($url));

        return [] === $rules ? '' : implode("\n\n", $rules)."\n";
    }

    /**
     * The URL of a file under the public path, each folder and the name percent-encoded.
     */
    public function url(CatalogueEntry $entry): string
    {
        return rtrim($this->publicPath, '/').'/'.implode('/', array_map(rawurlencode(...), explode('/', $entry->path)));
    }
}
