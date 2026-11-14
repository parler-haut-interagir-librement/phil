<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Infrastructure\Privacy;

use DateTimeImmutable;
use DateTimeInterface;
use Override;
use Phil\OnboardingBundle\Application\Onboarding;
use Phil\OnboardingBundle\Domain\Model\Progress;
use Phil\OnboardingBundle\Domain\Repository\ProgressRepository;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

/**
 * The onboarding's part of an access or erasure request, the data subject being the user identifier the progress is
 * kept under: the export lists where the person stands in each flow, the erasure forgets every progress of theirs.
 */
final readonly class OnboardingPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'onboarding';

    public function __construct(
        private Onboarding $onboarding,
        private ProgressRepository $progress,
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
        $progress = $this->progress->ofUser($subject->value);

        return [] === $progress ? [] : ['progress' => array_map($this->row(...), $progress)];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $this->onboarding->forget($subject->value);

        return ErasureReport::of(self::MODULE, [Progress::TABLE => ErasureEffect::Deleted]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Progress $progress): array
    {
        return [
            'flow' => $progress->flow,
            'steps' => $progress->steps(),
            'started_at' => $this->moment($progress->startedAt()),
            'updated_at' => $this->moment($progress->updatedAt()),
            'finished_at' => $this->moment($progress->finishedAt()),
            'dismissed_at' => $this->moment($progress->dismissedAt()),
        ];
    }

    private function moment(?DateTimeImmutable $moment): ?string
    {
        return $moment?->format(DateTimeInterface::ATOM);
    }
}
