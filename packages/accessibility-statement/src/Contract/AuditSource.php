<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement\Contract;

use Phil\AccessibilityStatement\Audit\AuditResult;

/**
 * Where the latest audit of the content comes from: the reports of a directory, a table, a service that runs
 * phil/accessibility-check over the pages of the site.
 */
interface AuditSource
{
    /**
     * The latest audit, or `null` when there is none yet.
     */
    public function latest(): ?AuditResult;
}
