<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Contract;

use Phil\NewsletterBundle\Domain\ValueObject\Site;

/**
 * The site a request is for: the configured one on a standalone site, the one the application resolves when it serves
 * several.
 */
interface CurrentSite
{
    public function site(): Site;
}
