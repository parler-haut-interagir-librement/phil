<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck;

/**
 * Where a finding is, for its author to find it: in HTML, the line and the path of the element; in a UJO document, the
 * block, its type and the field of its data, and the element inside that field when the field holds HTML. A field
 * left out is not known; a location without any field is the whole content.
 */
final readonly class Location
{
    /**
     * @param ?int    $line      the line of the element in the HTML, from 1
     * @param ?string $element   the path of the element, such as `p:nth-of-type(2) > a`
     * @param ?string $snippet   the opening tag of the element, cut to 160 characters
     * @param ?string $block     the identifier of the UJO block
     * @param ?string $blockType the type of the UJO block
     * @param ?string $pointer   the field of the document, such as `blocks[2].data.items[0].content`
     */
    public function __construct(
        public ?int $line = null,
        public ?string $element = null,
        public ?string $snippet = null,
        public ?string $block = null,
        public ?string $blockType = null,
        public ?string $pointer = null,
    ) {
    }

    /**
     * The location, read by a person: `block "a1" (image), blocks[2].data, img`, `line 3, p > img`, or `the content`.
     */
    public function describe(): string
    {
        $parts = [];

        if (null !== $this->block) {
            $parts[] = \sprintf('block "%s" (%s)', $this->block, $this->blockType ?? '?');
        }

        if (null !== $this->pointer) {
            $parts[] = $this->pointer;
        }

        if (null !== $this->line) {
            $parts[] = 'line '.$this->line;
        }

        if (null !== $this->element) {
            $parts[] = $this->element;
        }

        return [] === $parts ? 'the content' : implode(', ', $parts);
    }

    /**
     * The fields that are known, by name.
     *
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return array_filter([
            'line' => $this->line,
            'element' => $this->element,
            'snippet' => $this->snippet,
            'block' => $this->block,
            'blockType' => $this->blockType,
            'pointer' => $this->pointer,
        ], static fn (int|string|null $value): bool => null !== $value);
    }
}
