<?php

declare(strict_types=1);

namespace Phil\Lti\Service\Ags;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\Lti\Exception\InvalidData;
use Phil\Lti\Support\Fields;
use Phil\Url\Url;

/**
 * A column of the gradebook of a context: what it is graded out of, its label, and what it belongs to — a resource
 * link of the platform, a resource of the tool, a tag. Its `id` is its address, given by the platform.
 */
final readonly class LineItem
{
    public function __construct(
        public float $scoreMaximum,
        public string $label,
        public ?Url $id = null,
        public ?string $resourceId = null,
        public ?string $resourceLinkId = null,
        public ?string $tag = null,
        public ?DateTimeImmutable $startDateTime = null,
        public ?DateTimeImmutable $endDateTime = null,
        public ?bool $gradesReleased = null,
    ) {
        if ($scoreMaximum <= 0.0 || !is_finite($scoreMaximum)) {
            throw InvalidData::because('The maximum score of a line item is a number greater than 0.');
        }

        if ('' === trim($label) || \strlen($label) > Fields::MAX_STRING) {
            throw InvalidData::because('A line item has a label of 1 to 4,096 bytes.');
        }
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidData
     */
    public static function fromArray(array $data, bool $allowLocalhost = false): self
    {
        $fields = new Fields($data, inToken: false, allowLocalhost: $allowLocalhost);

        return new self(
            $fields->optionalNumber('scoreMaximum') ?? throw $fields->fail('A line item has its "scoreMaximum".'),
            $fields->string('label'),
            $fields->optionalUrl('id'),
            $fields->optionalString('resourceId', 255),
            $fields->optionalString('resourceLinkId', 255),
            $fields->optionalString('tag', 255),
            $fields->optionalDate('startDateTime'),
            $fields->optionalDate('endDateTime'),
            $fields->has('gradesReleased') ? $fields->bool('gradesReleased') : null,
        );
    }

    public function withId(Url $id): self
    {
        return new self($this->scoreMaximum, $this->label, $id, $this->resourceId, $this->resourceLinkId, $this->tag, $this->startDateTime, $this->endDateTime, $this->gradesReleased);
    }

    /**
     * @return array<string, string|float|bool>
     */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id?->toString(),
            'scoreMaximum' => $this->scoreMaximum,
            'label' => $this->label,
            'resourceId' => $this->resourceId,
            'resourceLinkId' => $this->resourceLinkId,
            'tag' => $this->tag,
            'startDateTime' => $this->startDateTime?->format(DateTimeInterface::ATOM),
            'endDateTime' => $this->endDateTime?->format(DateTimeInterface::ATOM),
            'gradesReleased' => $this->gradesReleased,
        ], static fn (string|float|bool|null $value): bool => null !== $value);
    }
}
