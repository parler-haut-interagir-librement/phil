<?php

declare(strict_types=1);

namespace Phil\Lti\DeepLinking;

use DateTimeImmutable;
use DateTimeInterface;
use Override;
use Phil\Lti\Contract\ContentItem;
use Phil\Lti\Exception\InvalidData;
use Phil\Lti\Support\Fields;
use Phil\Url\Url;

/**
 * A resource link of the tool for the platform to place: launched later as a resource link launch, at its address or
 * the tool's default, with its custom parameters and, when asked, a line item.
 */
final readonly class ResourceLinkItem implements ContentItem
{
    public const string TYPE = 'ltiResourceLink';

    /**
     * @param array<string, string> $custom
     */
    public function __construct(
        public ?Url $url = null,
        public ?string $title = null,
        public ?string $text = null,
        public array $custom = [],
        public ?LineItemRequest $lineItem = null,
        public ?Image $icon = null,
        public ?Image $thumbnail = null,
        public ?DateTimeImmutable $availableFrom = null,
        public ?DateTimeImmutable $availableUntil = null,
        public ?DateTimeImmutable $submissionFrom = null,
        public ?DateTimeImmutable $submissionUntil = null,
    ) {
        if ($availableFrom instanceof DateTimeImmutable && $availableUntil instanceof DateTimeImmutable && $availableUntil < $availableFrom) {
            throw InvalidData::because('A resource link is available until a moment after it is available from.');
        }

        if ($submissionFrom instanceof DateTimeImmutable && $submissionUntil instanceof DateTimeImmutable && $submissionUntil < $submissionFrom) {
            throw InvalidData::because('A resource link takes submissions until a moment after it takes them from.');
        }
    }

    /**
     * @internal
     */
    public static function read(Fields $fields): self
    {
        $available = $fields->optionalObject('available');
        $submission = $fields->optionalObject('submission');

        return new self(
            $fields->optionalUrl('url'),
            $fields->optionalString('title'),
            $fields->optionalString('text'),
            $fields->stringMap('custom'),
            LineItemRequest::read($fields->optionalObject('lineItem')),
            Image::read($fields->optionalObject('icon')),
            Image::read($fields->optionalObject('thumbnail')),
            $available?->optionalDate('startDateTime'),
            $available?->optionalDate('endDateTime'),
            $submission?->optionalDate('startDateTime'),
            $submission?->optionalDate('endDateTime'),
        );
    }

    #[Override]
    public function type(): string
    {
        return self::TYPE;
    }

    #[Override]
    public function toArray(): array
    {
        return array_filter([
            'type' => self::TYPE,
            'url' => $this->url?->toString(),
            'title' => $this->title,
            'text' => $this->text,
            'custom' => [] === $this->custom ? null : $this->custom,
            'lineItem' => $this->lineItem?->toArray(),
            'icon' => $this->icon?->toArray(),
            'thumbnail' => $this->thumbnail?->toArray(),
            'available' => $this->window($this->availableFrom, $this->availableUntil),
            'submission' => $this->window($this->submissionFrom, $this->submissionUntil),
        ], static fn (mixed $value): bool => null !== $value);
    }

    /**
     * @return array<string, string>|null
     */
    private function window(?DateTimeImmutable $from, ?DateTimeImmutable $until): ?array
    {
        $window = array_filter(['startDateTime' => $from?->format(DateTimeInterface::ATOM), 'endDateTime' => $until?->format(DateTimeInterface::ATOM)], static fn (?string $value): bool => null !== $value);

        return [] === $window ? null : $window;
    }
}
