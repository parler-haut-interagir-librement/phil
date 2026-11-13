<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Override;
use Phil\PagedMedia\Gotenberg\Exception\InvalidStage;

/**
 * Replaces the bookmarks, the outline viewers show beside the pages.
 */
final readonly class SetBookmarks implements Stage
{
    /** @var non-empty-list<Bookmark> */
    public array $bookmarks;

    public function __construct(Bookmark ...$bookmarks)
    {
        $bookmarks = array_values($bookmarks);

        if ([] === $bookmarks) {
            throw InvalidStage::because('Setting the bookmarks takes one bookmark at least.');
        }

        $this->bookmarks = $bookmarks;
    }

    #[Override]
    public function operation(): Operation
    {
        return Operation::SetBookmarks;
    }

    #[Override]
    public function options(): array
    {
        return ['bookmarks' => array_map(static fn (Bookmark $bookmark): array => $bookmark->toArray(), $this->bookmarks)];
    }

    #[Override]
    public function files(): array
    {
        return [];
    }
}
