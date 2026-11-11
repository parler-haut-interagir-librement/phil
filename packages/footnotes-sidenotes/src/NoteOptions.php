<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes;

use Phil\FootnotesSidenotes\Exception\InvalidOptions;

/**
 * How notes are numbered: where the numbering starts again, what counts as a chapter or a page marker, and the
 * numbering of each locale.
 */
final readonly class NoteOptions
{
    public Numbering $numbering;

    /** @var array<string, Numbering> */
    private array $locales;

    /**
     * @param int                      $chapterLevel the deepest level of `header` that starts a chapter, 1 to 6
     * @param list<string>             $pageMarkers  the block types that mark a new page
     * @param ?Numbering               $numbering    the numbering of any locale without its own, decimal by default
     * @param array<string, Numbering> $locales      the numbering of a locale or a language: `fr`, `fr-CA` or `fr_CA`
     *
     * @throws InvalidOptions
     */
    public function __construct(
        public Restart $restart = Restart::Document,
        public int $chapterLevel = 1,
        public array $pageMarkers = ['pageBreak'],
        ?Numbering $numbering = null,
        array $locales = [],
    ) {
        if ($chapterLevel < 1 || $chapterLevel > 6) {
            throw InvalidOptions::because('the chapter level goes from 1 to 6');
        }

        $normalized = [];

        foreach ($locales as $locale => $localeNumbering) {
            $normalized[$this->normalize($locale)] = $localeNumbering;
        }

        $this->numbering = $numbering ?? Numbering::decimal();
        $this->locales = $normalized;
    }

    /**
     * The numbering of a locale: its own, that of its language, or the default one.
     */
    public function numberingFor(?string $locale): Numbering
    {
        $normalized = $this->normalize($locale ?? '');

        return $this->locales[$normalized] ?? $this->locales[explode('-', $normalized)[0]] ?? $this->numbering;
    }

    private function normalize(string $locale): string
    {
        return strtolower(strtr($locale, '_', '-'));
    }
}
