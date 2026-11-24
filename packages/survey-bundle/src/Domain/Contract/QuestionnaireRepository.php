<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Domain\Contract;

use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;
use Phil\SurveyBundle\Domain\ValueObject\Questionnaire;

/**
 * Where the questionnaires are kept, each by its key, as the data of its JSON schema.
 */
interface QuestionnaireRepository
{
    /**
     * Adds the questionnaire, or replaces the one of its key.
     */
    public function save(Questionnaire $questionnaire): void;

    /**
     * @throws InvalidQuestionnaire when the stored data no longer reads as a questionnaire
     */
    public function find(string $key): ?Questionnaire;

    /**
     * @return list<string> the keys, in order
     */
    public function keys(): array;

    /**
     * @return array<string, int> the days each questionnaire with a retention keeps its responses, by key
     */
    public function retentions(): array;

    /**
     * Removes the questionnaire, its responses, their addenda, its counts and its marks.
     *
     * @return bool whether it was there
     */
    public function remove(string $key): bool;
}
