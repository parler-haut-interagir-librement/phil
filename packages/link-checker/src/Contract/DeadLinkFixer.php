<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Contract;

use Phil\LinkChecker\Report\CheckedLink;
use Phil\LinkChecker\Report\LinkReport;

/**
 * Repairs a broken or redirected link of a report, as an editor would in one click: a redirect created from a dead
 * internal address to the page that replaced it, or the link rewritten to its permanent target. The library ships no
 * implementation; a bundle or the application provides one, and calls it from its own screens.
 */
interface DeadLinkFixer
{
    /**
     * Whether this fixer can repair the link.
     */
    public function supports(LinkReport $report, CheckedLink $link): bool;

    public function fix(LinkReport $report, CheckedLink $link): void;
}
