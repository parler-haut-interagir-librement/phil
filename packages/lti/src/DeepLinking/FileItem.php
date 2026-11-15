<?php

declare(strict_types=1);

namespace Phil\Lti\DeepLinking;

use DateTimeImmutable;
use DateTimeInterface;
use Override;
use Phil\Lti\Contract\ContentItem;
use Phil\Lti\Support\Fields;
use Phil\Url\Url;

/**
 * A file for the platform to download and keep, before its address expires.
 */
final readonly class FileItem implements ContentItem
{
    public const string TYPE = 'file';

    public function __construct(
        public Url $url,
        public ?string $title = null,
        public ?string $text = null,
        public ?string $mediaType = null,
        public ?DateTimeImmutable $expiresAt = null,
    ) {
    }

    /**
     * @internal
     */
    public static function read(Fields $fields): self
    {
        return new self($fields->url('url'), $fields->optionalString('title'), $fields->optionalString('text'), $fields->optionalString('mediaType', 255), $fields->optionalDate('expiresAt'));
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
            'mediaType' => $this->mediaType,
            'expiresAt' => $this->expiresAt?->format(DateTimeInterface::ATOM),
        ], static fn (?string $value): bool => null !== $value);
    }
}
