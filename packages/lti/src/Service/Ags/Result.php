<?php

declare(strict_types=1);

namespace Phil\Lti\Service\Ags;

use Phil\Lti\Exception\InvalidData;
use Phil\Lti\Support\Fields;
use Phil\Url\Url;

/**
 * The result of a learner on a line item, as the platform's gradebook holds it.
 */
final readonly class Result
{
    public function __construct(
        public string $userId,
        public ?float $resultScore = null,
        public ?float $resultMaximum = null,
        public ?string $comment = null,
        public ?Url $id = null,
        public ?Url $scoreOf = null,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidData
     */
    public static function fromArray(array $data, bool $allowLocalhost = false): self
    {
        $fields = new Fields($data, inToken: false, allowLocalhost: $allowLocalhost);

        return new self($fields->string('userId', 255), $fields->optionalNumber('resultScore'), $fields->optionalNumber('resultMaximum'), $fields->optionalString('comment'), $fields->optionalUrl('id'), $fields->optionalUrl('scoreOf'));
    }

    /**
     * @return array<string, string|float>
     */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id?->toString(),
            'scoreOf' => $this->scoreOf?->toString(),
            'userId' => $this->userId,
            'resultScore' => $this->resultScore,
            'resultMaximum' => $this->resultMaximum,
            'comment' => $this->comment,
        ], static fn (string|float|null $value): bool => null !== $value);
    }
}
