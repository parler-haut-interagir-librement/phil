<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Infrastructure\Privacy;

use Override;
use Phil\DoctrineBehaviours\VersionEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

/**
 * The frozen versions' part of an access or erasure request, the data subject being the name of the blame or the
 * owner identifier: the export counts the versions that still hold the person's personal fields; the erasure takes
 * those fields out of them and reports the tables of the versions. The blame and the owner redact the same versions
 * before they replace the name they are found by, so this module finds them already redacted when it runs after.
 */
final readonly class VersionsPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'behaviours.versions';

    public function __construct(
        private VersionEraser $eraser,
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString(self::MODULE);
    }

    #[Override]
    public function export(DataSubject $subject): array
    {
        $found = $this->eraser->find($subject->value);

        return 0 === $found ? [] : ['versions_with_personal_fields' => $found];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $this->eraser->redact($subject->value);

        return ErasureReport::of(self::MODULE, Stores::of($this->eraser->tables(), self::MODULE, ErasureEffect::Redacted));
    }
}
