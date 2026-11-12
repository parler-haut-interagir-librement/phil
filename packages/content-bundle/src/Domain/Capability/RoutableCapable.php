<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Capability;

use Phil\Slug\Slug;

/**
 * A row that has an address of its own: a slug, one per locale.
 *
 * The slug lives on the localized row — `RoutableDataMapper` writes it there —, so each language has its own
 * address. Which path the slug sits under (`/news/{slug}`), how a request finds its row, and what happens to
 * the old address when a slug changes are the application's routing.
 *
 * **Nothing here keeps two rows from sharing a slug.** Every version and both stages of one locale carry the
 * same slug, so a unique index on the row table would refuse the copy publishing makes. The application
 * checks that a slug is free in its locale before it submits one.
 *
 * Use `RoutableTrait` for the implementation.
 */
interface RoutableCapable
{
    public function getSlug(): ?Slug;

    public function setSlug(?Slug $slug): void;
}
