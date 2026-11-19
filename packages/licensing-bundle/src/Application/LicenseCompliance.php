<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Application;

use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\Catalog\Requirement;
use Phil\LicensingBundle\Domain\Compliance\ViolationCode;
use Phil\LicensingBundle\Domain\Model\LicenseDeclaration;
use Phil\LicensingBundle\Domain\ValueObject\Attribution;
use Phil\LicensingBundle\Domain\ValueObject\ComplianceReport;
use Phil\LicensingBundle\Domain\ValueObject\License;
use Phil\LicensingBundle\Domain\ValueObject\Violation;

/**
 * Whether a declaration gives what its license, and the licenses of the resources it attributes, require.
 *
 * The work's license asks for its declarations: its text when the license must go with the work or no address gives
 * it, the work's notice, the Reserved Material, the Product Identity, the designated content. Each resource's license
 * asks for its credit, its notice and a statement of the changes made to it, even to say there were none, and a
 * share-alike license asks for the work to be under it too. What the check cannot see — that the notices and the
 * changes stated are true — stays the publisher's.
 */
final readonly class LicenseCompliance
{
    private const array DECLARATIONS = [
        [Requirement::ReservedMaterial, ViolationCode::MissingReservedMaterial],
        [Requirement::ProductIdentity, ViolationCode::MissingProductIdentity],
        [Requirement::DesignatedContent, ViolationCode::MissingDesignatedContent],
        [Requirement::CopyrightNotice, ViolationCode::MissingNotice],
    ];

    public function __construct(
        private LicenseCatalog $catalog,
    ) {
    }

    public function check(LicenseDeclaration $declaration): ComplianceReport
    {
        $report = new ComplianceReport();
        $license = $this->catalog->find($declaration->license());

        if (!$license instanceof License) {
            $report = $report->with(new Violation(ViolationCode::UnknownLicense, $declaration->license()));
        } else {
            $report = $this->checkWork($report, $declaration, $license);
        }

        foreach ($declaration->attributions() as $index => $attribution) {
            $report = $this->checkResource($report, $declaration, $index, $attribution);
        }

        return $report;
    }

    private function checkWork(ComplianceReport $report, LicenseDeclaration $declaration, License $license): ComplianceReport
    {
        $unlocated = $license->requires(Requirement::LicenseNotice) && !$license->isLocated();

        if (null === $declaration->licenseText() && ($unlocated || $license->requires(Requirement::FullText))) {
            $report = $report->with(new Violation(ViolationCode::MissingLicenseText, $license->code));
        }

        $given = [
            Requirement::ReservedMaterial->value => $declaration->reservedMaterial(),
            Requirement::ProductIdentity->value => $declaration->productIdentity(),
            Requirement::DesignatedContent->value => $declaration->designatedContent(),
            Requirement::CopyrightNotice->value => $declaration->notice(),
        ];

        foreach (self::DECLARATIONS as [$requirement, $code]) {
            if ($license->requires($requirement) && null === $given[$requirement->value]) {
                $report = $report->with(new Violation($code, $license->code));
            }
        }

        return $report;
    }

    private function checkResource(ComplianceReport $report, LicenseDeclaration $declaration, int $index, Attribution $attribution): ComplianceReport
    {
        $license = $this->catalog->find($attribution->license);

        if (!$license instanceof License) {
            return $report->with(new Violation(ViolationCode::UnknownLicense, $attribution->license, $index, $attribution->title));
        }

        $found = [
            ViolationCode::MissingCredit->value => $license->requires(Requirement::Attribution) && !$attribution->credits(),
            ViolationCode::MissingResourceNotice->value => $license->requires(Requirement::CopyrightNotice) && null === $attribution->notice,
            ViolationCode::MissingChanges->value => $license->requires(Requirement::IndicateChanges) && null === $attribution->changes,
            ViolationCode::ShareAlikeConflict->value => $license->requires(Requirement::ShareAlike) && $license->code !== $declaration->license(),
        ];

        foreach ($found as $code => $violated) {
            if ($violated) {
                $report = $report->with(new Violation(ViolationCode::from($code), $license->code, $index, $attribution->title));
            }
        }

        return $report;
    }
}
