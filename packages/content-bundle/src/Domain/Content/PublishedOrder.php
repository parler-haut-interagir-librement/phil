<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Content;

/**
 * The order of the published rows a `PublishedRowQuery` finds. Every order ends on the root's identifier, so a page
 * of results follows the one before it without overlap.
 */
enum PublishedOrder
{
    /**
     * By the root's identifier: a stable order, to walk a kind of content page by page.
     */
    case Root;

    /**
     * The most recently published first, by the localized row's `workflow_published`; those never published last.
     * A row class without the workflow capability is read by its root's identifier.
     */
    case LatestPublished;

    /**
     * By the localized row's slug. A row class without the routable capability is read by its root's identifier.
     */
    case Slug;
}
