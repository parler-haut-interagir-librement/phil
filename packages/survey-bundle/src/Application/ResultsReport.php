<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Application;

/**
 * The results of a questionnaire — about a subject, when given —: how many responses it counts, the minimum it needs
 * before showing anything, and the result of each question counted. Below the minimum, the report is withheld and
 * holds no question.
 */
final readonly class ResultsReport
{
    /**
     * @param list<QuestionResult> $questions
     */
    public function __construct(
        public int $responses,
        public int $minimum,
        public bool $withheld,
        public array $questions,
    ) {
    }
}
