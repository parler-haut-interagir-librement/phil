<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes;

/**
 * The words of the notes that readers hear or read, as plain text: the title of the notes region, and the accessible
 * names of a reference, a backlink and a toggle. `%label%` stands for the note's label, `%occurrence%` for the rank of
 * a reference. Build one with the application's translations for another language.
 */
final readonly class NoteLabels
{
    public function __construct(
        public string $title = 'Notes',
        public string $reference = 'Note %label%',
        public string $backlink = 'Back to reference %label%',
        public string $repeatedBacklink = 'Back to reference %label%, occurrence %occurrence%',
        public string $toggle = 'Show note %label%',
    ) {
    }

    public static function english(): self
    {
        return new self();
    }

    public static function french(): self
    {
        return new self('Notes', 'Note %label%', 'Retour au renvoi %label%', 'Retour au renvoi %label%, occurrence %occurrence%', 'Afficher la note %label%');
    }

    /**
     * The labels of a locale: French for `fr` and its regions, English for any other.
     */
    public static function forLocale(string $locale): self
    {
        return 'fr' === strtolower(substr($locale, 0, 2)) ? self::french() : self::english();
    }

    /**
     * The accessible name of a reference to a note.
     */
    public function referenceName(string $label): string
    {
        return strtr($this->reference, ['%label%' => $label]);
    }

    /**
     * The accessible name of a backlink: the note's only reference, or one of several.
     */
    public function backlinkName(string $label, int $occurrence, bool $repeated): string
    {
        return strtr($repeated ? $this->repeatedBacklink : $this->backlink, ['%label%' => $label, '%occurrence%' => (string) $occurrence]);
    }

    /**
     * The accessible name of the toggle of a margin note on a narrow viewport.
     */
    public function toggleName(string $label): string
    {
        return strtr($this->toggle, ['%label%' => $label]);
    }
}
