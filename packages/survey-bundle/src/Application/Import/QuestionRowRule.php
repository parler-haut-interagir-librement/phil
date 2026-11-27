<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Application\Import;

use Override;
use Phil\ImportWizard\Contract\RowRule;
use Phil\ImportWizard\Plan\CellError;
use Phil\SurveyBundle\Application\Exception\InvalidQuestionRow;

/**
 * The check of a whole row of the CSV file of questions in the dry run: the row is read as a question, so that a row
 * the questionnaire would refuse is refused in the preview, with the cell at fault, before anything is written.
 */
final readonly class QuestionRowRule implements RowRule
{
    #[Override]
    public static function name(): string
    {
        return QuestionCsvProfile::TYPE;
    }

    #[Override]
    public static function fromArray(array $data): static
    {
        return new self();
    }

    #[Override]
    public function check(array $values): array
    {
        try {
            QuestionRows::read($values);
        } catch (InvalidQuestionRow $invalidQuestionRow) {
            return [CellError::refusedByTarget($invalidQuestionRow->column, $invalidQuestionRow->getMessage())];
        }

        return [];
    }

    #[Override]
    public function toArray(): array
    {
        return [];
    }
}
