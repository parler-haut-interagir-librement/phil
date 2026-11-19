<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Infrastructure\Privacy;

use Override;
use Phil\DoctrineBehaviours\BlameEraser;
use Phil\DoctrineBehaviours\VersionEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

/**
 * The blame's part of an access or erasure request, the data subject being the name the blame records: the export
 * counts the rows that blame the person, by table; the erasure replaces the name by the configured pseudonym, or by
 * nobody, in every blamed table, and reports each of them. With the versions on, the versions of the person are
 * redacted first, while the blame still finds them.
 */
final readonly class BlamePrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'behaviours.blame';

    public function __construct(
        private BlameEraser $eraser,
        private ?string $replacement = null,
        private ?VersionEraser $versions = null,
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

        return [] === $found ? [] : ['rows_by_table' => $found];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $this->versions?->redact($subject->value);
        $this->eraser->replace($subject->value, $this->replacement);

        return ErasureReport::of(self::MODULE, Stores::of($this->eraser->tables(), self::MODULE, null === $this->replacement ? ErasureEffect::Redacted : ErasureEffect::Pseudonymised));
    }
}
