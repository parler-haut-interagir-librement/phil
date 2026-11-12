<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Application\Contract;

/**
 * Whether a violation was already announced within a window, so that a page reporting the same violation for each of
 * its visitors dispatches one event, not thousands.
 */
interface ReportDeduplicator
{
    /**
     * Whether the fingerprint is new within the window, remembering it when it is.
     */
    public function isNew(string $fingerprint): bool;
}
