<?php

declare(strict_types=1);

namespace Phil\Lti\DeepLinking;

use Override;
use Phil\Lti\Contract\ContentItem;
use Phil\Lti\Support\Fields;
use Phil\Url\Url;

/**
 * A plain link to a page, opened by the platform outside LTI.
 */
final readonly class LinkItem implements ContentItem
{
    public const string TYPE = 'link';

    public function __construct(
        public Url $url,
        public ?string $title = null,
        public ?string $text = null,
        public ?Image $icon = null,
        public ?Image $thumbnail = null,
    ) {
    }

    /**
     * @internal
     */
    public static function read(Fields $fields): self
    {
        return new self($fields->url('url'), $fields->optionalString('title'), $fields->optionalString('text'), Image::read($fields->optionalObject('icon')), Image::read($fields->optionalObject('thumbnail')));
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
            'icon' => $this->icon?->toArray(),
            'thumbnail' => $this->thumbnail?->toArray(),
        ], static fn (mixed $value): bool => null !== $value);
    }
}
