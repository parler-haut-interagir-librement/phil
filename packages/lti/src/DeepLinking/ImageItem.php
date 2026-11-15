<?php

declare(strict_types=1);

namespace Phil\Lti\DeepLinking;

use Override;
use Phil\Lti\Contract\ContentItem;
use Phil\Lti\Support\Fields;
use Phil\Url\Url;

/**
 * An image for the platform to show.
 */
final readonly class ImageItem implements ContentItem
{
    public const string TYPE = 'image';

    public function __construct(
        public Url $url,
        public ?string $title = null,
        public ?string $text = null,
        public ?int $width = null,
        public ?int $height = null,
    ) {
    }

    /**
     * @internal
     */
    public static function read(Fields $fields): self
    {
        return new self($fields->url('url'), $fields->optionalString('title'), $fields->optionalString('text'), $fields->optionalInt('width'), $fields->optionalInt('height'));
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
            'url' => $this->url->toString(),
            'title' => $this->title,
            'text' => $this->text,
            'width' => $this->width,
            'height' => $this->height,
        ], static fn (string|int|null $value): bool => null !== $value);
    }
}
