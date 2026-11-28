<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Application;

use Override;
use Phil\EmailTemplatesBundle\Application\Contract\SiteScope;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;

/**
 * Always the same site: the one the configuration names, or the shared scope.
 */
final readonly class FixedSiteScope implements SiteScope
{
    public function __construct(
        private Site $site,
    ) {
    }

    #[Override]
    public function current(): Site
    {
        return $this->site;
    }
}
