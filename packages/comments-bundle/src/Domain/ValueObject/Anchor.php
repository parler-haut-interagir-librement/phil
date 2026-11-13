<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Domain\ValueObject;

use Phil\CommentsBundle\Domain\Exception\InvalidComment;

/**
 * Where a passage is: in a document of blocks, the block's identifier and the path of the field in its data —
 * `text`, `caption`, `items.0.content`, `content.1.2` —; in a plain text, neither. Offsets count characters (Unicode
 * code points) of the field's text, its markup left out; the passage itself and up to 32 characters on each side are
 * kept, so that it can be found again once the text around it has changed.
 */
final readonly class Anchor
{
    public const int MAX_QUOTE = 2000;

    public const int CONTEXT = 32;

    private const string BLOCK = '/^[A-Za-z0-9_-]{1,64}$/D';

    private const string FIELD = '/^[A-Za-z0-9_]{1,64}(?:\.[A-Za-z0-9_]{1,64}){0,15}$/D';

    private function __construct(
        public ?string $block,
        public ?string $field,
        public int $start,
        public int $end,
        public string $quote,
        public string $prefix,
        public string $suffix,
    ) {
    }

    /**
     * A passage starting at a character offset; the end follows from its length, and the context keeps only the 32
     * characters next to it.
     *
     * @throws InvalidComment when the block, the field, the offset or the passage cannot be kept
     */
    public static function of(?string $block, ?string $field, int $start, string $quote, string $prefix = '', string $suffix = ''): self
    {
        if (null !== $block && 1 !== preg_match(self::BLOCK, $block)) {
            throw InvalidComment::because('A block is identified by 1 to 64 letters, digits, "_" or "-".');
        }

        if (null !== $field && 1 !== preg_match(self::FIELD, $field)) {
            throw InvalidComment::because('A field is a path of 1 to 16 names of letters, digits or "_", separated by dots.');
        }

        if (null !== $block && null === $field) {
            throw InvalidComment::because('A passage of a block names the field it is in.');
        }

        if ($start < 0) {
            throw InvalidComment::because('A passage starts at an offset of zero or more characters.');
        }

        if (!mb_check_encoding([$quote, $prefix, $suffix], 'UTF-8')) {
            throw InvalidComment::because('A passage and its context are UTF-8 texts.');
        }

        $length = mb_strlen($quote);

        if ($length < 1 || $length > self::MAX_QUOTE) {
            throw InvalidComment::because(\sprintf('A passage is 1 to %d characters.', self::MAX_QUOTE));
        }

        return new self($block, $field, $start, $start + $length, $quote, mb_substr($prefix, -self::CONTEXT), mb_substr($suffix, 0, self::CONTEXT));
    }

    /**
     * Whether the passage is in this block and this field — both `null` for a plain text.
     */
    public function isIn(?string $block, ?string $field): bool
    {
        return $this->block === $block && $this->field === $field;
    }

    /**
     * @return array{block: ?string, field: ?string, start: int, end: int, quote: string, prefix: string, suffix: string}
     */
    public function toArray(): array
    {
        return [
            'block' => $this->block,
            'field' => $this->field,
            'start' => $this->start,
            'end' => $this->end,
            'quote' => $this->quote,
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
        ];
    }
}
