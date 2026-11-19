<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Application;

use Phil\LicensingBundle\Application\Event\ExportChecking;
use Phil\LicensingBundle\Application\Exception\ExportBlocked;
use Phil\LicensingBundle\Domain\Compliance\ViolationCode;
use Phil\LicensingBundle\Domain\Contract\Licensable;
use Phil\LicensingBundle\Domain\Contract\LicenseDeclarationRepository;
use Phil\LicensingBundle\Domain\Model\LicenseDeclaration;
use Phil\LicensingBundle\Domain\ValueObject\ComplianceReport;
use Phil\LicensingBundle\Domain\ValueObject\SubjectReference;
use Phil\LicensingBundle\Domain\ValueObject\Violation;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * What an application calls before it exports a work — a PDF, an EPUB, a print file —: a subject without a declaration,
 * or whose declaration does not comply, is not exported. Listeners of `ExportChecking` add their own violations.
 */
final readonly class ExportGuard
{
    public function __construct(
        private LicenseDeclarationRepository $declarations,
        private LicenseCompliance $compliance,
        private ?EventDispatcherInterface $dispatcher = null,
    ) {
    }

    public function check(SubjectReference|Licensable $subject): ComplianceReport
    {
        $subject = $subject instanceof Licensable ? $subject->licenseSubject() : $subject;
        $declaration = $this->declarations->declarationOf($subject);
        $report = $declaration instanceof LicenseDeclaration
            ? $this->compliance->check($declaration)
            : new ComplianceReport([new Violation(ViolationCode::NoDeclaration)]);

        if (!$this->dispatcher instanceof EventDispatcherInterface) {
            return $report;
        }

        $event = new ExportChecking($subject, $declaration, $report);
        $this->dispatcher->dispatch($event);

        return $event->report();
    }

    /**
     * @throws ExportBlocked
     */
    public function assertExportable(SubjectReference|Licensable $subject): void
    {
        $subject = $subject instanceof Licensable ? $subject->licenseSubject() : $subject;
        $report = $this->check($subject);

        if (!$report->isCompliant()) {
            throw new ExportBlocked($subject, $report);
        }
    }
}
