<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Domain\Contract;

use Phil\FaqBundle\Domain\Model\Question;
use Phil\FaqBundle\Domain\Model\QuestionTranslation;
use Phil\FaqBundle\Domain\Model\Section;
use Phil\FaqBundle\Domain\Model\SectionTranslation;
use Phil\FaqBundle\Domain\ValueObject\VoteCount;

/**
 * Where the sections, the questions and their translations are kept.
 */
interface FaqRepository
{
    /**
     * Every section, in order.
     *
     * @return list<Section>
     */
    public function sections(): array;

    public function section(string $id): ?Section;

    public function sectionByKey(string $key): ?Section;

    /**
     * The questions of a section, in order.
     *
     * @return list<Question>
     */
    public function questionsOf(Section $section): array;

    /**
     * Every question, in the order of their sections and of their places.
     *
     * @return list<Question>
     */
    public function questions(): array;

    public function question(string $id): ?Question;

    public function questionByAnchor(string $anchor): ?Question;

    /**
     * The titles of the sections, by section identifier and locale.
     *
     * @return array<string, array<string, SectionTranslation>>
     */
    public function sectionTranslations(): array;

    /**
     * The translations of the questions, by question identifier and locale.
     *
     * @param bool $publishedOnly whether only the published ones are read
     *
     * @return array<string, array<string, QuestionTranslation>>
     */
    public function questionTranslations(bool $publishedOnly): array;

    public function sectionTranslation(Section $section, string $locale): ?SectionTranslation;

    public function questionTranslation(Question $question, string $locale): ?QuestionTranslation;

    /**
     * Keeps new or changed sections, questions and translations, all at once.
     */
    public function save(Section|SectionTranslation|Question|QuestionTranslation ...$objects): void;

    /**
     * Removes a section with its questions and every translation, or a question with its translations.
     */
    public function remove(Section|Question $object): void;

    /**
     * Adds one to the count of useful or not useful answers of a question, in one statement, whatever else counts at
     * the same time.
     *
     * @return VoteCount|null the totals after the vote, null when the question does not exist
     */
    public function countVote(string $questionId, bool $helpful): ?VoteCount;

    /**
     * Runs the work in one transaction: every change it makes is kept, or none when it throws.
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function transactional(callable $work): mixed;
}
