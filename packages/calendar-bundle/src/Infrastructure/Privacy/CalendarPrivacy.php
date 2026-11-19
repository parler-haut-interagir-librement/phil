<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Infrastructure\Privacy;

use DateTimeInterface;
use Override;
use Phil\CalendarBundle\Application\CalendarChoices;
use Phil\CalendarBundle\Application\PersonalFeeds;
use Phil\CalendarBundle\Domain\Model\Attendance;
use Phil\CalendarBundle\Domain\Model\CalendarChoice;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\Model\PersonalFeedToken;
use Phil\CalendarBundle\Domain\Repository\AttendanceRepository;
use Phil\CalendarBundle\Domain\Repository\EventRepository;
use Phil\CalendarBundle\Domain\ValueObject\Member;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

/**
 * The calendar's part of an access or erasure request, the data subject being the reference of a member of the kind
 * configured, `member` by default: the export lists the member's choices, when their feed's secret was issued — never
 * the secret —, what they attend and the events they proposed; the erasure deletes the choices, the secret and the
 * attendances, and the events proposed no longer name them. Running it again finds nothing more and reports the same.
 */
final readonly class CalendarPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'calendar';

    /** The proposals listed at most. */
    private const int PROPOSALS = 1000;

    public function __construct(
        private CalendarChoices $choices,
        private PersonalFeeds $feeds,
        private AttendanceRepository $attendances,
        private EventRepository $events,
        private string $memberKind = 'member',
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
        $member = $this->member($subject);

        if (!$member instanceof Member) {
            return [];
        }

        return array_filter([
            'choices' => array_map(static fn (CalendarChoice $choice): array => [
                'source' => $choice->source()->value,
                'value' => $choice->value(),
                'hidden' => $choice->isHidden(),
                'chosen_at' => $choice->chosenAt()->format(DateTimeInterface::ATOM),
            ], $this->choices->of($member)->choices),
            'feed_issued_at' => $this->feeds->issuedAt($member)?->format(DateTimeInterface::ATOM),
            'attendances' => $this->attendances->attendedBy($member),
            'proposals' => array_map(static fn (Event $event): array => [
                'event' => $event->id,
                'title' => $event->title(),
                'state' => $event->getProposalState(),
            ], $this->events->proposalsOf($member, self::PROPOSALS)),
        ], static fn (mixed $part): bool => null !== $part && [] !== $part);
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $member = $this->member($subject);

        if ($member instanceof Member) {
            $this->choices->forgetMember($member);
            $this->feeds->revoke($member);
            $this->attendances->forgetMember($member);
            $this->events->forgetProposer($member);
        }

        return ErasureReport::of(self::MODULE, [
            CalendarChoice::TABLE => ErasureEffect::Deleted,
            PersonalFeedToken::TABLE => ErasureEffect::Deleted,
            Attendance::TABLE => ErasureEffect::Deleted,
            'phil_calendar_event.proposed_by' => ErasureEffect::Redacted,
        ]);
    }

    /**
     * The member a subject names, or null for a reference no member can have.
     */
    private function member(DataSubject $subject): ?Member
    {
        return 1 === preg_match('/^[\w.:-]{1,64}$/D', $subject->value) ? Member::of($this->memberKind, $subject->value) : null;
    }
}
