<?php

declare(strict_types=1);

namespace Phil\Lti\DeepLinking;

use Phil\Lti\Exception\InvalidData;
use Phil\Lti\Support\Fields;

/**
 * The line item a resource link asks the platform to create with it, when the platform accepts line items.
 */
final readonly class LineItemRequest
{
    public function __construct(
        public float $scoreMaximum,
        public ?string $label = null,
        public ?string $resourceId = null,
        public ?string $tag = null,
        public ?bool $gradesReleased = null,
    ) {
        if ($scoreMaximum <= 0.0) {
            throw InvalidData::because('The maximum score of a line item is greater than 0.');
        }
    }

    /**
     * @internal
     */
    public static function read(?Fields $fields): ?self
    {
        if (!$fields instanceof Fields) {
            return null;
        }

        return new self(
            $fields->optionalNumber('scoreMaximum') ?? throw $fields->fail('A line item has its "scoreMaximum".'),
            $fields->optionalString('label'),
            $fields->optionalString('resourceId', 255),
            $fields->optionalString('tag', 255),
            $fields->has('gradesReleased') ? $fields->bool('gradesReleased') : null,
        );
    }

    /**
     * @return array<string, string|float|bool>
     */
    public function toArray(): array
    {
        return array_filter([
            'scoreMaximum' => $this->scoreMaximum,
            'label' => $this->label,
            'resourceId' => $this->resourceId,
            'tag' => $this->tag,
            'gradesReleased' => $this->gradesReleased,
        ], static fn (string|float|bool|null $value): bool => null !== $value);
    }
}
