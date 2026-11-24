<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Application\Contract;

/**
 * The site the current request belongs to, for an application that serves several: its key picks the site's policy in
 * the configuration. The bundle's own answers `null`, the same policy everywhere.
 */
interface CurrentSite
{
    /**
     * The key of the current site, as the configuration's `sites` names it, or `null`.
     */
    public function key(): ?string;
}
