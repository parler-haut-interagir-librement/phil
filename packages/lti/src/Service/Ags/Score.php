<?php

declare(strict_types=1);

namespace Phil\Lti\Service\Ags;

use DateTimeImmutable;
use Phil\Lti\Exception\InvalidData;
use Phil\Lti\Support\Fields;

/**
 * A score the tool sends for a learner on a line item: the score given out of a maximum, when there is one yet, where
 * the learner and the grading are, and the moment the score was made, by which the platform keeps the latest.
 */
final readonly class Score
{
    public function __construct(
        public string $userId,
        public ActivityProgress $activityProgress,
        public GradingProgress $gradingProgress,
        public DateTimeImmutable $timestamp,
        public ?float $scoreGiven = null,
        public ?float $scoreMaximum = null,
        public ?string $comment = null,
    ) {
        if ('' === $userId || \strlen($userId) > 255) {
            throw InvalidData::because('A score names the learner by 1 to 255 bytes.');
        }

        if (null !== $scoreGiven && (null === $scoreMaximum || $scoreGiven < 0.0 || !is_finite($scoreGiven))) {
            throw InvalidData::because('A score given is a number of 0 or more, with its maximum.');
        }

        if (null !== $scoreMaximum && ($scoreMaximum <= 0.0 || !is_finite($scoreMaximum))) {
            throw InvalidData::because('The maximum of a score is a number greater than 0.');
        }

        if (null !== $comment && \strlen($comment) > Fields::MAX_STRING) {
            throw InvalidData::because('The comment of a score is 4,096 bytes at most.');
        }
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidData
     */
    public static function fromArray(array $data): self
    {
        $fields = new Fields($data, inToken: false);

        return new self(
            $fields->string('userId', 255),
            ActivityProgress::tryFrom($fields->string('activityProgress', 32)) ?? throw $fields->fail('The "activityProgress" of a score is not one of AGS.'),
            GradingProgress::tryFrom($fields->string('gradingProgress', 32)) ?? throw $fields->fail('The "gradingProgress" of a score is not one of AGS.'),
            $fields->optionalDate('timestamp') ?? throw $fields->fail('A score carries its "timestamp".'),
            $fields->optionalNumber('scoreGiven'),
            $fields->optionalNumber('scoreMaximum'),
            $fields->optionalString('comment'),
        );
    }

    /**
     * @return array<string, string|float>
     */
    public function toArray(): array
    {
        return array_filter([
            'userId' => $this->userId,
            'scoreGiven' => $this->scoreGiven,
            'scoreMaximum' => $this->scoreMaximum,
            'comment' => $this->comment,
            'timestamp' => $this->timestamp->format('Y-m-d\TH:i:s.vP'),
            'activityProgress' => $this->activityProgress->value,
            'gradingProgress' => $this->gradingProgress->value,
        ], static fn (string|float|null $value): bool => null !== $value);
    }
}
