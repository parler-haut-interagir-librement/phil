<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Infrastructure\Privacy;

use Override;
use Phil\DoctrineBehaviours\BlameSuspension;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

/**
 * An eraser of phil/privacy-bundle that also exports: the erasure runs with the blame suspended, the export as it is.
 */
final readonly class SuspendBlameWithExport implements PersonalDataEraser, PersonalDataExporter
{
    public function __construct(
        private PersonalDataEraser&PersonalDataExporter $eraser,
        private BlameSuspension $suspension,
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return $this->eraser->module();
    }

    #[Override]
    public function export(DataSubject $subject): array
    {
        return $this->eraser->export($subject);
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        return $this->suspension->during(fn (): ErasureReport => $this->eraser->erase($subject));
    }
}
