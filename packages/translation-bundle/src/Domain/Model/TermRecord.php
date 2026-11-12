<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\TranslationBundle\Domain\Exception\InvalidRecord;
use Phil\TranslationMemory\Glossary\Term;
use Phil\TranslationMemory\LanguageTag;

/**
 * One term of a glossary, as a row identified by both languages and the term: its allowed and refused translations,
 * whether case matters, a note for translators, and the inflected forms of the term and of its translations.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
final class TermRecord
{
    public const string TABLE = 'phil_translation_term';

    public const int MAX_TERM = 255;

    /**
     * @param list<string> $translations
     * @param list<string> $forbidden
     * @param list<string> $forms
     * @param list<string> $translationForms
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'source_language', length: 35)]
        private readonly string $sourceLanguage,
        #[ORM\Id]
        #[ORM\Column(name: 'target_language', length: 35)]
        private readonly string $targetLanguage,
        #[ORM\Id]
        #[ORM\Column(name: 'term', length: self::MAX_TERM)]
        private readonly string $term,
        #[ORM\Column(name: 'translations', type: 'json')]
        private array $translations,
        #[ORM\Column(name: 'forbidden', type: 'json')]
        private array $forbidden,
        #[ORM\Column(name: 'case_sensitive')]
        private bool $caseSensitive,
        #[ORM\Column(name: 'note', type: 'text', nullable: true)]
        private ?string $note,
        #[ORM\Column(name: 'forms', type: 'json')]
        private array $forms = [],
        #[ORM\Column(name: 'translation_forms', type: 'json')]
        private array $translationForms = [],
    ) {
    }

    /**
     * @throws InvalidRecord when the term is longer than 255 bytes
     */
    public static function of(LanguageTag $sourceLanguage, LanguageTag $targetLanguage, Term $term): self
    {
        if (\strlen($term->source) > self::MAX_TERM) {
            throw InvalidRecord::because(\sprintf('A glossary term is at most %d bytes.', self::MAX_TERM));
        }

        return new self($sourceLanguage->value, $targetLanguage->value, $term->source, $term->translations, $term->forbidden, $term->caseSensitive, $term->note, $term->forms, $term->translationForms);
    }

    /**
     * Takes the translations, case, note and forms of the same term.
     *
     * @throws InvalidRecord when the term is another
     */
    public function change(Term $term): void
    {
        if ($term->source !== $this->term) {
            throw InvalidRecord::because(\sprintf('The row of term "%s" cannot take term "%s".', $this->term, $term->source));
        }

        $this->translations = $term->translations;
        $this->forbidden = $term->forbidden;
        $this->caseSensitive = $term->caseSensitive;
        $this->note = $term->note;
        $this->forms = $term->forms;
        $this->translationForms = $term->translationForms;
    }

    public function term(): Term
    {
        return new Term($this->term, $this->translations, $this->forbidden, $this->caseSensitive, $this->note, $this->forms, $this->translationForms);
    }

    public function source(): string
    {
        return $this->term;
    }

    public function sourceLanguage(): LanguageTag
    {
        return LanguageTag::of($this->sourceLanguage);
    }

    public function targetLanguage(): LanguageTag
    {
        return LanguageTag::of($this->targetLanguage);
    }
}
