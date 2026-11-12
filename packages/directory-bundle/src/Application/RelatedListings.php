<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Application;

use Phil\ContentBundle\Application\Publication\PublishedContent;

/**
 * The listings related to one, by one relation and one way: those it points at, under the relation's title, or those
 * that point at it, under its inverse title. Their genre names their pages.
 */
final readonly class RelatedListings
{
    /**
     * @param list<PublishedContent> $listings by slug
     */
    public function __construct(
        public Relation $relation,
        public bool $inverse,
        public Genre $genre,
        public array $listings,
    ) {
    }

    public function title(): string
    {
        return $this->inverse ? $this->relation->inverseTitle : $this->relation->title;
    }
}
