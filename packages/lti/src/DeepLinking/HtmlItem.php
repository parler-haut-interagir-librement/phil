<?php

declare(strict_types=1);

namespace Phil\Lti\DeepLinking;

use Override;
use Phil\Lti\Contract\ContentItem;
use Phil\Lti\Support\Fields;

/**
 * A fragment of HTML for the platform to embed. The platform decides what of it to keep: a platform that reads one
 * sanitises it before showing it.
 */
final readonly class HtmlItem implements ContentItem
{
    public const string TYPE = 'html';

    public const int MAX_BYTES = 65_536;

    public function __construct(
        public string $html,
        public ?string $title = null,
        public ?string $text = null,
    ) {
    }

    /**
     * @internal
     */
    public static function read(Fields $fields): self
    {
        return new self($fields->string('html', self::MAX_BYTES), $fields->optionalString('title'), $fields->optionalString('text'));
    }

    #[Override]
    public function type(): string
    {
        return self::TYPE;
    }

    #[Override]
    public function toArray(): array
    {
        return array_filter(['type' => self::TYPE, 'html' => $this->html, 'title' => $this->title, 'text' => $this->text], static fn (?string $value): bool => null !== $value);
    }
}
