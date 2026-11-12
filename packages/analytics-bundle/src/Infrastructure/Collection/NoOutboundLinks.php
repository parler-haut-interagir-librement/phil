<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Infrastructure\Collection;

use Override;
use Phil\AnalyticsBundle\Application\Contract\OutboundLinkDirectory;
use Phil\AnalyticsBundle\Domain\Measure\DeclaredOutboundLink;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;

/**
 * No declared outbound link: a click on a link to another site is counted by host only. An application that writes
 * links into its authors' pages replaces this adapter.
 */
final readonly class NoOutboundLinks implements OutboundLinkDirectory
{
    #[Override]
    public function find(SiteId $site, string $reference): ?DeclaredOutboundLink
    {
        return null;
    }
}
