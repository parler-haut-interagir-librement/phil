<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Application;

use Phil\TranslationBundle\Application\Exception\NotFound;
use Phil\TranslationBundle\Domain\Exception\InvalidRecord;
use Phil\TranslationBundle\Domain\Model\TermRecord;
use Phil\TranslationBundle\Domain\Repository\TermRepository;
use Phil\TranslationMemory\Exception\InvalidTbx;
use Phil\TranslationMemory\Glossary\Contract\Inflector;
use Phil\TranslationMemory\Glossary\Glossary;
use Phil\TranslationMemory\Glossary\Term;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Tbx\TbxReader;
use Phil\TranslationMemory\Tbx\TbxWriter;

/**
 * The glossaries kept by the bundle, one per pair of languages: terms put and removed, the glossary that finds and
 * checks them — with the forms of an inflector, when one is given —, and the glossary exchanged as TBX.
 */
final readonly class Glossaries
{
    public function __construct(
        private TermRepository $terms,
        private ?Inflector $inflector = null,
    ) {
    }

    public function glossary(LanguageTag $sourceLanguage, LanguageTag $targetLanguage): Glossary
    {
        return new Glossary($sourceLanguage, $targetLanguage, array_map(static fn (TermRecord $record): Term => $record->term(), $this->terms->forLanguages($sourceLanguage, $targetLanguage)), $this->inflector);
    }

    /**
     * The terms of a pair of languages as a TBX file, as they are kept — without the forms an inflector adds.
     */
    public function export(LanguageTag $sourceLanguage, LanguageTag $targetLanguage): string
    {
        return new TbxWriter()->write(new Glossary($sourceLanguage, $targetLanguage, array_map(static fn (TermRecord $record): Term => $record->term(), $this->terms($sourceLanguage, $targetLanguage))));
    }

    /**
     * Puts the terms of a TBX file into the glossary of a pair of languages: new terms added, terms it has replaced.
     *
     * @return int how many terms were put
     *
     * @throws InvalidTbx    when the file cannot be read
     * @throws InvalidRecord when a term is longer than 255 bytes
     */
    public function import(LanguageTag $sourceLanguage, LanguageTag $targetLanguage, string $tbx): int
    {
        $glossary = new TbxReader()->read($tbx, $sourceLanguage, $targetLanguage);

        foreach ($glossary->terms as $term) {
            $this->put($sourceLanguage, $targetLanguage, $term);
        }

        return \count($glossary->terms);
    }

    /**
     * The terms of a pair of languages, as rows, in the order of their text.
     *
     * @return list<TermRecord>
     */
    public function terms(LanguageTag $sourceLanguage, LanguageTag $targetLanguage): array
    {
        $records = $this->terms->forLanguages($sourceLanguage, $targetLanguage);
        usort($records, static fn (TermRecord $a, TermRecord $b): int => strcmp($a->source(), $b->source()));

        return $records;
    }

    /**
     * Keeps a term: a new one, or the new translations, case and note of a term the glossary has.
     *
     * @throws InvalidRecord when the term is longer than 255 bytes
     */
    public function put(LanguageTag $sourceLanguage, LanguageTag $targetLanguage, Term $term): TermRecord
    {
        $record = $this->terms->get($sourceLanguage, $targetLanguage, $term->source);

        if ($record instanceof TermRecord) {
            $record->change($term);
        } else {
            $record = TermRecord::of($sourceLanguage, $targetLanguage, $term);
        }

        $this->terms->save($record);

        return $record;
    }

    /**
     * @throws NotFound
     */
    public function remove(LanguageTag $sourceLanguage, LanguageTag $targetLanguage, string $term): void
    {
        $this->terms->remove($this->terms->get($sourceLanguage, $targetLanguage, $term) ?? throw NotFound::term($term, $sourceLanguage->value, $targetLanguage->value));
    }
}
