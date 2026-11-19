<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Infrastructure\Privacy;

use DateTimeInterface;
use Override;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use Phil\VirtualClassroom\ParticipantId;
use Phil\VirtualClassroomBundle\Application\Contract\ClassroomStore;
use Phil\VirtualClassroomBundle\Infrastructure\Dbal\ClassroomTables;

/**
 * The attendance in an access or erasure request: the person is the participant, named by the stable identifier of
 * their account. The export gives each stay — its room, when they arrived and left —; the erasure deletes the stays,
 * and runs again without harm. The rooms and the recordings name nobody, and stay.
 */
final readonly class ClassroomPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'virtual_classroom';

    /**
     * @param non-empty-string $prefix
     */
    public function __construct(
        private ClassroomStore $store,
        private string $prefix = ClassroomTables::PREFIX,
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
        $participant = ParticipantId::tryFrom($subject->value);
        $stays = $participant instanceof ParticipantId ? $this->store->staysOf($participant) : [];

        return [] === $stays ? [] : ['stays' => array_map(static fn (array $stay): array => [
            'room' => $stay['room'],
            'joined_at' => $stay['interval']->joinedAt->format(DateTimeInterface::ATOM),
            'left_at' => $stay['interval']->leftAt?->format(DateTimeInterface::ATOM),
        ], $stays)];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $participant = ParticipantId::tryFrom($subject->value);

        if ($participant instanceof ParticipantId) {
            $this->store->forget($participant);
        }

        return ErasureReport::of(self::MODULE, [ClassroomTables::stays($this->prefix) => ErasureEffect::Deleted]);
    }
}
