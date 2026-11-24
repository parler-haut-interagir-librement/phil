<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Domain\ValueObject;

/**
 * What recording a response did: kept, refused because a quota it counts in is full, or refused because the respondent
 * answered already. A refused response leaves nothing behind — neither the response, nor a count, nor a mark.
 */
final readonly class RecordOutcome
{
    private function __construct(
        public bool $recorded,
        public ?string $fullQuota,
        public bool $alreadyAnswered,
    ) {
    }

    public static function recorded(): self
    {
        return new self(true, null, false);
    }

    public static function quotaFull(string $quota): self
    {
        return new self(false, $quota, false);
    }

    public static function alreadyAnswered(): self
    {
        return new self(false, null, true);
    }
}
