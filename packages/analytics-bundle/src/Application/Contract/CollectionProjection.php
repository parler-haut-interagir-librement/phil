<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Application\Contract;

use Phil\AnalyticsBundle\Domain\ValueObject\CollectionSite;
use Phil\AnalyticsBundle\Domain\ValueObject\Origin;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;

/**
 * The collection projection: for each site, its declared origins, its declared routes and its status.
 *
 * **Collection reads this projection, never the application that maintains it.** The application writes it when
 * a site's declaration changes and when a site is suspended, reinstated or retired; the collection endpoint only
 * reads it, with a role that can read nothing else.
 */
interface CollectionProjection
{
    /**
     * The site that declares this origin, or `null`: a request whose origin no site declares is refused
     * without a write.
     */
    public function siteDeclaring(Origin $origin): ?CollectionSite;

    /**
     * The site of this reference, or `null` when the projection does not hold it: what the application's server counts
     * for a site is checked against the same declaration as what its pages send.
     */
    public function site(SiteId $site): ?CollectionSite;
}
