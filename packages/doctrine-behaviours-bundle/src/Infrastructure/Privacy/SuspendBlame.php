<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Infrastructure\Privacy;

use Override;
use Phil\DoctrineBehaviours\BlameSuspension;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

/**
 * An eraser of phil/privacy-bundle, run with the blame suspended: its flushes write no name.
 */
final readonly class SuspendBlame implements PersonalDataEraser
{
    public function __construct(
        private PersonalDataEraser $eraser,
        private BlameSuspension $suspension,
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return $this->eraser->module();
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        return $this->suspension->during(fn (): ErasureReport => $this->eraser->erase($subject));
    }
}
