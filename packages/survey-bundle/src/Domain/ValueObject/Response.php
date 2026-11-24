<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Domain\ValueObject;

use DateTimeImmutable;

/**
 * A response kept: the answers of the questions shown, the computed variables, the ending reached, and, for a session's
 * feedback, the subject it is about. An anonymous response has no respondent, a random identifier and the day only as
 * its instant; an identified one names its respondent.
 */
final readonly class Response
{
    /**
     * @param array<string, mixed> $answers        the stored value of each question answered, by name
     * @param array<string, mixed> $computed       the computed variables, by name
     * @param string|null          $addendumDigest the SHA-256 of the token that adds to the response, when it takes addenda
     */
    public function __construct(
        public string $id,
        public string $questionnaire,
        public ?string $subject,
        public ?string $respondent,
        public string $ending,
        public array $answers,
        public array $computed,
        public ?string $addendumDigest,
        public DateTimeImmutable $submittedAt,
    ) {
    }
}
