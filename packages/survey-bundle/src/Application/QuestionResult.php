<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Application;

use Phil\Poll\Result;
use Phil\SurveyBundle\Domain\ValueObject\Question;

/**
 * The result of one question: how many answered it and the count phil/poll gives — of each row, for a matrix —, or
 * nothing when fewer answered it than the questionnaire's minimum, so that a small group does not show who answered
 * what.
 */
final readonly class QuestionResult
{
    /**
     * @param array<string, Result> $rows the count of each row of a matrix, by row
     */
    public function __construct(
        public Question $question,
        public int $answered,
        public bool $withheld,
        public ?Result $result = null,
        public array $rows = [],
    ) {
    }
}
