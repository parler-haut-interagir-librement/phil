<?php

declare(strict_types=1);

namespace Phil\Lti\DeepLinking;

use Phil\Lti\Support\Fields;
use Phil\Url\Url;

/**
 * An icon or a thumbnail of a content item.
 */
final readonly class Image
{
    public function __construct(
        public Url $url,
        public ?int $width = null,
        public ?int $height = null,
    ) {
    }

    /**
     * @internal
     */
    public static function read(?Fields $fields): ?self
    {
        return $fields instanceof Fields ? new self($fields->url('url'), $fields->optionalInt('width'), $fields->optionalInt('height')) : null;
    }

    /**
     * @return array<string, string|int>
     */
    public function toArray(): array
    {
        return array_filter(['url' => $this->url->toString(), 'width' => $this->width, 'height' => $this->height], static fn (string|int|null $value): bool => null !== $value);
    }
}
