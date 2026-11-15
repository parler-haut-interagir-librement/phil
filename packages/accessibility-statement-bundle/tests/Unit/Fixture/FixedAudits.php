<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Tests\Unit\Fixture;

use Override;
use Phil\AccessibilityStatement\Audit\AuditResult;
use Phil\AccessibilityStatement\Contract\AuditSource;

/**
 * The audit given, or none.
 */
final readonly class FixedAudits implements AuditSource
{
    public function __construct(
        private ?AuditResult $audit = null,
    ) {
    }

    #[Override]
    public function latest(): ?AuditResult
    {
        return $this->audit;
    }
}
