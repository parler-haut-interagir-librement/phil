<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement\Audit;

use Phil\AccessibilityCheck\Report;
use Phil\AccessibilityStatement\Exception\InvalidStatement;

/**
 * One page of an audit and the report phil/accessibility-check wrote for it. The page is named as the audit names it:
 * an address, a path, a file.
 */
final readonly class AuditedPage
{
    public const int MAX_PAGE_LENGTH = 2048;

    public function __construct(
        public string $page,
        public Report $report,
    ) {
        if ('' === $page || \strlen($page) > self::MAX_PAGE_LENGTH) {
            throw InvalidStatement::because('audit_page', \sprintf('An audited page is named in 1 to %d bytes.', self::MAX_PAGE_LENGTH));
        }
    }
}
