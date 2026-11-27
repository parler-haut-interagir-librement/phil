<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Application\Import;

use Phil\SurveyBundle\Domain\ValueObject\Question;

/**
 * A row of the CSV file of questions, read: the question, the name of the section it goes to — none to leave it where
 * it is, or to put a new one in the last section — and the title of that section, should the row create it.
 */
final readonly class ImportedQuestion
{
    public function __construct(
        public Question $question,
        public ?string $section = null,
        public ?string $sectionTitle = null,
    ) {
    }
}
