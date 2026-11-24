<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Domain\ValueObject;

use DateTimeImmutable;

/**
 * What a respondent adds to a response after sending it.
 */
final readonly class Addendum
{
    public const int MAX_LENGTH = 5000;

    public function __construct(
        public string $response,
        public string $text,
        public DateTimeImmutable $addedAt,
    ) {
    }
}
