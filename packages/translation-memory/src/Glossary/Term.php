<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Glossary;

use Phil\TranslationMemory\Exception\InvalidTerm;
use Phil\TranslationMemory\Glossary\Contract\Inflector;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Text;

/**
 * One entry of a glossary: a term of the source language, the translations allowed in the target — none for a term
 * kept as it is, a name, a brand —, the translations refused, whether case matters, a note for translators, and the
 * inflected forms of the term and of its translations — `hit point` for `hit points`, `point de vie` for
 * `points de vie` —, which are found and accepted as the term and its translations are.
 */
final readonly class Term
{
    public string $source;

    /**
     * @var list<string>
     */
    public array $translations;

    /**
     * @var list<string>
     */
    public array $forbidden;

    /**
     * @var list<string> the inflected forms of the term, without the term itself
     */
    public array $forms;

    /**
     * @var list<string> the inflected forms of the translations — or of the term, when it is kept —, without the
     *                   translations themselves
     */
    public array $translationForms;

    /**
     * @param list<string> $translations
     * @param list<string> $forbidden
     * @param list<string> $forms            the inflected forms of the term
     * @param list<string> $translationForms the inflected forms of its translations
     *
     * @throws InvalidTerm when the term, one of its translations or one of their forms has no words
     */
    public function __construct(
        string $source,
        array $translations = [],
        array $forbidden = [],
        public bool $caseSensitive = false,
        public ?string $note = null,
        array $forms = [],
        array $translationForms = [],
    ) {
        foreach ([$source, ...$translations, ...$forbidden, ...$forms, ...$translationForms] as $text) {
            if (!mb_check_encoding($text, 'UTF-8') || '' === Text::normalize($text)) {
                throw InvalidTerm::because('A glossary term, its translations and their forms are texts of UTF-8 that hold words.');
            }
        }

        $this->source = Text::normalize($source);
        $this->translations = array_map(Text::normalize(...), $translations);
        $this->forbidden = array_map(Text::normalize(...), $forbidden);
        $this->forms = $this->others(array_map(Text::normalize(...), $forms), [$this->source]);
        $this->translationForms = $this->others(array_map(Text::normalize(...), $translationForms), $this->expected());
    }

    /**
     * The term with the forms an inflector gives for it and for its translations, added to those it has.
     */
    public function inflected(Inflector $inflector, LanguageTag $sourceLanguage, LanguageTag $targetLanguage): self
    {
        $forms = [...$this->forms, ...$inflector->forms($this->source, $sourceLanguage)];
        $translationForms = $this->translationForms;

        foreach ($this->translations as $translation) {
            $translationForms = [...$translationForms, ...$inflector->forms($translation, $targetLanguage)];
        }

        return new self($this->source, $this->translations, $this->forbidden, $this->caseSensitive, $this->note, $forms, $this->isKept() ? $forms : $translationForms);
    }

    /**
     * The words the glossary finds for this term in a source: the term and its forms.
     *
     * @return non-empty-list<string>
     */
    public function variants(): array
    {
        return [$this->source, ...$this->forms];
    }

    /**
     * The words a target may hold for this term: the expected translations and their forms.
     *
     * @return non-empty-list<string>
     */
    public function accepted(): array
    {
        return [...$this->expected(), ...$this->translationForms];
    }

    /**
     * Whether the term stays as it is in the target.
     */
    public function isKept(): bool
    {
        return [] === $this->translations;
    }

    /**
     * The texts a target must hold for this term: its translations, or the term itself when it is kept.
     *
     * @return non-empty-list<string>
     */
    public function expected(): array
    {
        return [] === $this->translations ? [$this->source] : $this->translations;
    }

    /**
     * The texts, normalized, without duplicates and without those already given.
     *
     * @param list<string> $texts
     * @param list<string> $given
     *
     * @return list<string>
     */
    private function others(array $texts, array $given): array
    {
        return array_values(array_diff(array_unique($texts), $given));
    }
}
