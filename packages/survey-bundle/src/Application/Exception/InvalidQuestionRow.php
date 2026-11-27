<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Application\Exception;

use Phil\SurveyBundle\Domain\Exception\SurveyException;
use RuntimeException;

/**
 * A row of the CSV file of questions that is not a question: the column at fault — none when it is the row — and a
 * reason that never repeats a text of the row.
 */
final class InvalidQuestionRow extends RuntimeException implements SurveyException
{
    public function __construct(
        public readonly ?string $column,
        string $reason,
    ) {
        parent::__construct($reason);
    }
}
