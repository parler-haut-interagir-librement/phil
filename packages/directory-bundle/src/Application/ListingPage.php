<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Application;

use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\DirectoryBundle\Domain\Model\Listing;

/**
 * A listing found by its address: the listing, what readers of the locale are served, normalized — or the slug its
 * address moved to.
 */
final readonly class ListingPage
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public ContentReference $reference,
        public Listing $listing,
        public array $data = [],
        public ?string $movedTo = null,
    ) {
    }
}
