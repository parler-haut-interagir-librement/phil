<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Application\Contract;

use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;

/**
 * The site the current request, message or command acts for: the active tenant when `phil/tenancy-bundle` is there,
 * the configured site otherwise, the shared scope when none is configured.
 */
interface SiteScope
{
    public function current(): Site;
}
