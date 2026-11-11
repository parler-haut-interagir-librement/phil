<?php

declare(strict_types=1);

namespace Phil\Ujo\Schema;

use Override;
use Phil\Ujo\Contract\Sized;
use Phil\Ujo\Exception\InvalidDocument;

/**
 * A text with inline HTML, kept as the sanitizer's profile allows: `rich` by default, `raw` for a `raw` block. A text
 * longer than the normalizer allows for the profile is refused before it is sanitized.
 */
final readonly class Html implements Sized
{
    public function __construct(
        private string $profile = 'rich',
    ) {
    }

    #[Override]
    public function normalize(mixed $value, string $path, Context $context): string
    {
        if (!\is_string($value)) {
            throw InvalidDocument::at($path, 'a text is expected');
        }

        $maxLength = $context->maxHtmlLength($this->profile);

        if (mb_strlen($value) > $maxLength) {
            throw InvalidDocument::at($path, \sprintf('the HTML is longer than %d characters', $maxLength));
        }

        return $context->sanitize($value, $this->profile);
    }

    /**
     * The characters a reader sees: the text without its tags, its character references read, its white space at
     * both ends left out.
     */
    #[Override]
    public function size(mixed $value): int
    {
        return \is_string($value) ? Plain::visibleLength(html_entity_decode(strip_tags($value), \ENT_QUOTES | \ENT_HTML5, 'UTF-8')) : 0;
    }

    #[Override]
    public function unit(): string
    {
        return 'characters';
    }
}
