<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Application\Event;

use Phil\LicensingBundle\Domain\Model\LicenseDeclaration;
use Phil\LicensingBundle\Domain\ValueObject\ComplianceReport;
use Phil\LicensingBundle\Domain\ValueObject\SubjectReference;
use Phil\LicensingBundle\Domain\ValueObject\Violation;

/**
 * Dispatched each time the export guard checks a subject, after the compliance check: a listener adds the violations
 * of its own rules — an attribution page left out of the export, a resource without its file —, and the export is
 * blocked by them as by the others.
 */
final class ExportChecking
{
    public function __construct(
        public readonly SubjectReference $subject,
        public readonly ?LicenseDeclaration $declaration,
        private ComplianceReport $report,
    ) {
    }

    public function report(): ComplianceReport
    {
        return $this->report;
    }

    public function addViolation(Violation $violation): void
    {
        $this->report = $this->report->with($violation);
    }
}
