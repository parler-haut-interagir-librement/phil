<?php

declare(strict_types=1);

namespace Phil\Fonts\Css;

use Phil\Fonts\Exception\InvalidFontFace;
use Phil\Fonts\Font;
use Phil\Fonts\FontFile;
use Phil\Fonts\Format;

/**
 * An `@font-face` rule written from the metadata of a face: its family, its sources, its weight, slope and width —
 * as ranges for a variable font — how it shows while loading and, on request, the characters it covers.
 *
 * Every string is written between double quotes with `"`, `\`, `<`, `>` and control characters escaped, so a name or
 * a URL taken from a font file can neither end the rule nor the `<style>` element that holds it.
 */
final readonly class FontFaceRule
{
    /**
     * @param list<string>                $locals
     * @param list<array{string, Format}> $urls
     * @param list<array{int, int}>       $unicodeRanges
     */
    private function __construct(
        private Font $font,
        private string $family,
        private array $locals,
        private array $urls = [],
        private FontDisplay $display = FontDisplay::Swap,
        private array $unicodeRanges = [],
    ) {
    }

    /**
     * A rule for a face, under its family, with its full and PostScript names as `local()` sources and no file yet.
     */
    public static function for(Font $font): self
    {
        $locals = [];

        foreach ([$font->fullName(), $font->postScriptName()] as $name) {
            if (null !== $name && !\in_array($name, $locals, true)) {
                $locals[] = $name;
            }
        }

        return new self($font, $font->family(), $locals);
    }

    /**
     * A rule for a face of a file served at a URL, in the file's format.
     */
    public static function forFile(FontFile $file, string $url, int $face = 0): self
    {
        return self::for($file->face($face))->withUrl($url, $file->format);
    }

    /**
     * The family the rule declares, in place of the one the font names.
     */
    public function withFamily(string $family): self
    {
        return new self($this->font, $family, $this->locals, $this->urls, $this->display, $this->unicodeRanges);
    }

    /**
     * A file the browser may download, after the sources already given: list the smallest format first.
     */
    public function withUrl(string $url, Format $format): self
    {
        return new self($this->font, $this->family, $this->locals, [...$this->urls, [$url, $format]], $this->display, $this->unicodeRanges);
    }

    /**
     * No `local()` source: the browser always downloads the file, even when a font of the same name is installed.
     */
    public function withoutLocal(): self
    {
        return new self($this->font, $this->family, [], $this->urls, $this->display, $this->unicodeRanges);
    }

    public function withDisplay(FontDisplay $display): self
    {
        return new self($this->font, $this->family, $this->locals, $this->urls, $display, $this->unicodeRanges);
    }

    /**
     * A `unicode-range` descriptor: the runs given, or those the font's character map covers.
     *
     * @param list<array{int, int}>|null $ranges
     */
    public function withUnicodeRange(?array $ranges = null): self
    {
        return new self($this->font, $this->family, $this->locals, $this->urls, $this->display, $ranges ?? $this->font->unicodeRanges);
    }

    public function toCss(): string
    {
        if ('' === $this->family) {
            throw InvalidFontFace::because('An @font-face rule needs a family; the font names none, so give one with withFamily().');
        }

        $sources = [
            ...array_map(static fn (string $name): string => 'local('.self::string($name).')', $this->locals),
            ...array_map(static fn (array $url): string => 'url('.self::string($url[0]).') format("'.$url[1]->value.'")', $this->urls),
        ];

        if ([] === $sources) {
            throw InvalidFontFace::because('An @font-face rule needs a source: a URL, or the local names of the font.');
        }

        $descriptors = [
            'font-family' => self::string($this->family),
            'src' => implode(', ', $sources),
            'font-weight' => $this->range($this->font->weightRange(), ''),
            'font-style' => $this->font->style->value,
            'font-stretch' => $this->range($this->font->stretchRange(), '%'),
            'font-display' => $this->display->value,
        ];

        if ([] !== $this->unicodeRanges) {
            $descriptors['unicode-range'] = implode(', ', array_map(
                static fn (array $range): string => \sprintf($range[0] === $range[1] ? 'U+%X' : 'U+%X-%X', $range[0], $range[1]),
                $this->unicodeRanges,
            ));
        }

        $css = "@font-face {\n";

        foreach ($descriptors as $descriptor => $value) {
            $css .= '    '.$descriptor.': '.$value.";\n";
        }

        return $css.'}';
    }

    /**
     * @param array{int|float, int|float} $range
     */
    private function range(array $range, string $unit): string
    {
        $first = round($range[0], 3).$unit;

        return $range[0] === $range[1] ? $first : $first.' '.round($range[1], 3).$unit;
    }

    private static function string(string $value): string
    {
        return '"'.preg_replace_callback(
            '/[\x00-\x1F\x7F"\\\\<>]/',
            static fn (array $match): string => \in_array($match[0], ['"', '\\'], true) ? '\\'.$match[0] : \sprintf('\\%X ', \ord($match[0])),
            $value,
        ).'"';
    }
}
