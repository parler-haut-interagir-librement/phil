<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Application;

use Phil\SurveyBundle\Domain\ValueObject\Ending;

/**
 * What a respondent gets back: the ending reached — its profile, its redirect checked again —, whether the response
 * was kept or a full quota screened it out, and, when the questionnaire takes addenda, the token that adds to the
 * response, which only the respondent holds: the bundle keeps its digest.
 */
final readonly class Receipt
{
    /**
     * @param array<string, mixed> $computed the computed variables, such as a score
     */
    public function __construct(
        public Ending $ending,
        public bool $kept,
        public ?string $response = null,
        public ?string $redirect = null,
        public ?string $addendumToken = null,
        public array $computed = [],
        public ?string $fullQuota = null,
    ) {
    }
}
