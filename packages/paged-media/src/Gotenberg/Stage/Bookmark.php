<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Phil\PagedMedia\Gotenberg\Exception\InvalidStage;
use Phil\PagedMedia\Gotenberg\Read\JsonValue;

/**
 * An entry of the outline viewers show beside the pages, with the entries nested under it; `set-bookmarks` writes them
 * and `/forms/pdf/bookmarks` reads them in the same shape.
 */
final readonly class Bookmark
{
    /**
     * @param list<self> $children
     */
    public function __construct(
        public string $title,
        public int $pageNumber,
        public array $children = [],
    ) {
        if ('' === trim($title)) {
            throw InvalidStage::because('A bookmark has a title.');
        }

        if ($pageNumber < 1) {
            throw InvalidStage::because(\sprintf('A bookmark points at a page counted from 1; %d is not.', $pageNumber));
        }
    }

    /**
     * @internal
     */
    public static function fromJson(JsonValue $json): self
    {
        return new self($json->string('title'), $json->int('pageNumber'), array_map(self::fromJson(...), $json->optionalList('children')));
    }

    /**
     * @return array{title: string, pageNumber: int, children?: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        $entry = ['title' => $this->title, 'pageNumber' => $this->pageNumber];

        if ([] !== $this->children) {
            $entry['children'] = array_map(static fn (self $child): array => $child->toArray(), $this->children);
        }

        return $entry;
    }
}
