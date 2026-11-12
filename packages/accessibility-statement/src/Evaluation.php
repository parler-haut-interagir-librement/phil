<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement;

use DateTimeImmutable;
use Phil\AccessibilityStatement\Exception\InvalidStatement;

/**
 * The last evaluation of the content: when, how, how many pages it covered, and where its report is.
 */
final readonly class Evaluation
{
    public function __construct(
        public DateTimeImmutable $evaluatedAt,
        public EvaluationMethod $method = EvaluationMethod::SelfAssessment,
        public int $pages = 0,
        public ?string $reportUrl = null,
    ) {
        if ($pages < 0) {
            throw InvalidStatement::because('evaluation_pages', \sprintf('An evaluation covers 0 or more pages; got %d.', $pages));
        }

        if (null !== $reportUrl) {
            Address::check($reportUrl, 'report page');
        }
    }
}
