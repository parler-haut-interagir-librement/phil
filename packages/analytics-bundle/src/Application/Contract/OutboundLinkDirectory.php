<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Application\Contract;

use Phil\AnalyticsBundle\Domain\Measure\DeclaredOutboundLink;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;

/**
 * The links an application wrote into its authors' pages.
 */
interface OutboundLinkDirectory
{
    /**
     * The link of this reference on this site, or `null`: the click is then counted by host only.
     */
    public function find(SiteId $site, string $reference): ?DeclaredOutboundLink;
}
