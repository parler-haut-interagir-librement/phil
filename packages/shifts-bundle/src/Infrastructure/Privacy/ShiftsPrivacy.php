<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Infrastructure\Privacy;

use DateTimeImmutable;
use Override;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use Phil\Shifts\Format\SwapFormat;
use Phil\Shifts\Roster\Roster;
use Phil\ShiftsBundle\Domain\Model\GuardianContact;
use Phil\ShiftsBundle\Domain\Repository\AbsenceRepository;
use Phil\ShiftsBundle\Domain\Repository\GuardianRepository;
use Phil\ShiftsBundle\Domain\Repository\RosterRepository;
use Phil\ShiftsBundle\Domain\Repository\SwapRepository;
use Phil\ShiftsBundle\Infrastructure\Doctrine\ShiftsTables;

/**
 * A volunteer's shift data in the export and the erasure of phil/privacy-bundle: the shifts on which they stand, with
 * their status and its instant, the offers of the swap board they made or took, the contact of their guardian with the
 * consent, and their unannounced absences. The erasure takes them off every roster — nobody is promoted, no event is
 * dispatched — and deletes those offers, that contact and those absences; the shifts remain for the others.
 */
final readonly class ShiftsPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'shifts';

    public function __construct(
        private RosterRepository $rosters,
        private SwapRepository $swaps,
        private GuardianRepository $guardians,
        private AbsenceRepository $absences,
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
        $export = [];

        foreach ($this->rosters->ofVolunteer($subject->value) as $roster) {
            $export['shifts'][] = $this->entry($roster, $subject->value);
        }

        foreach ($this->swaps->ofVolunteer($subject->value) as $offer) {
            $export['swaps'][] = SwapFormat::toArray($offer);
        }

        $guardian = $this->guardians->find($subject->value);

        if ($guardian instanceof GuardianContact) {
            $export['guardian'] = [
                'name' => $guardian->name,
                'phone' => $guardian->phone,
                'email' => $guardian->email,
                'recorded_by' => $guardian->recordedBy,
                'consented_at' => $guardian->consentedAt instanceof DateTimeImmutable ? $this->instant($guardian->consentedAt) : null,
                'consent_recorded_by' => $guardian->consentRecordedBy,
            ];
        }

        foreach ($this->absences->ofVolunteer($subject->value) as $absence) {
            $export['absences'][] = [
                'shift' => $absence->shiftId,
                'recorded_at' => $this->instant($absence->recordedAt),
                'recorded_by' => $absence->recordedBy,
                'lifted_at' => null === $absence->liftedAt ? null : $this->instant($absence->liftedAt),
                'lifted_by' => $absence->liftedBy,
            ];
        }

        return $export;
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $this->swaps->eraseVolunteer($subject->value);
        $this->rosters->eraseVolunteer($subject->value);
        $this->guardians->remove($subject->value);
        $this->absences->eraseVolunteer($subject->value);

        return ErasureReport::of(self::MODULE, [
            ShiftsTables::PLACES => ErasureEffect::Deleted,
            ShiftsTables::SWAPS => ErasureEffect::Deleted,
            ShiftsTables::GUARDIANS => ErasureEffect::Deleted,
            ShiftsTables::ABSENCES => ErasureEffect::Deleted,
        ]);
    }

    /**
     * @return array<string, string|int|null>
     */
    private function entry(Roster $roster, string $volunteer): array
    {
        foreach ($roster->places as $place) {
            if ($place->volunteer === $volunteer) {
                return [
                    'shift' => $roster->shift->id,
                    'title' => $roster->shift->title,
                    'starts_at' => $this->instant($roster->shift->startsAt),
                    'ends_at' => $this->instant($roster->shift->endsAt),
                    'minutes' => $roster->shift->minutes,
                    'status' => $place->status->value,
                    'since' => $this->instant($place->at),
                    'assigned_by' => $place->assignedBy,
                ];
            }
        }

        return ['shift' => $roster->shift->id];
    }

    private function instant(DateTimeImmutable $instant): string
    {
        return $instant->format('Y-m-d\\TH:i:s\\Z');
    }
}
