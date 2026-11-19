<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\Tests\Benchmark;

use DateTimeImmutable;
use Phil\Url\Url;
use Phil\VirtualClassroom\Attendance\AttendanceSheet;
use Phil\VirtualClassroom\Attendance\Interval;
use Phil\VirtualClassroom\BigBlueButton\BigBlueButton;
use Phil\VirtualClassroom\Jitsi\Jitsi;
use Phil\VirtualClassroom\OpenedRoom;
use Phil\VirtualClassroom\Participant;
use Phil\VirtualClassroom\ParticipantId;
use Phil\VirtualClassroom\Role;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroom\Webhook;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * The hot paths of a class: a join link written for each participant who enters, the attendance of a large session
 * posted at its end, and the share of the session each participant attended.
 */
final readonly class ClassroomBench
{
    private BigBlueButton $bbb;

    private Jitsi $jitsi;

    private OpenedRoom $room;

    private Participant $participant;

    private Webhook $attendance;

    /** @var list<Interval> */
    private array $intervals;

    private DateTimeImmutable $start;

    private DateTimeImmutable $end;

    public function __construct()
    {
        $clock = new MockClock(new DateTimeImmutable('@1700000000'));
        $secret = 'bench-secret-of-the-server-01234';
        $this->bbb = new BigBlueButton(new MockHttpClient(), Url::fromString('https://bbb.example.com/bigbluebutton/api/'), $secret, $clock, signedAnalytics: false);
        $this->jitsi = Jitsi::selfHosted(Url::fromString('https://meet.example.com'), 'app1', $secret, $clock);
        $this->room = new OpenedRoom(RoomId::fromString('room1'), true, '1700000000000');
        $this->participant = new Participant(ParticipantId::fromString('user-7'), 'Ada Lovelace');
        $attendees = array_map(static fn (int $i): array => ['ext_user_id' => 'u'.$i, 'joins' => [1_700_000_000 + $i, 1_700_001_000 + $i], 'leaves' => [1_700_000_500 + $i, 1_700_003_000 + $i]], range(1, 500));
        $this->attendance = new Webhook('POST', [], [], json_encode(['meeting_id' => 'room1', 'data' => ['attendees' => $attendees]], \JSON_THROW_ON_ERROR));
        $this->intervals = array_map(static fn (int $i): Interval => new Interval(new DateTimeImmutable('@'.(1_700_000_000 + $i * 30)), new DateTimeImmutable('@'.(1_700_000_600 + $i * 30))), range(1, 200));
        $this->start = new DateTimeImmutable('@1700000000');
        $this->end = new DateTimeImmutable('@1700007200');
    }

    public function benchABigBlueButtonJoinLink(): void
    {
        $this->bbb->joinUrl($this->room, $this->participant, Role::Attendee);
    }

    public function benchAJitsiJoinLink(): void
    {
        $this->jitsi->joinUrl($this->room, $this->participant, Role::Moderator);
    }

    public function benchTheAttendanceOfFiveHundredAttendees(): void
    {
        $this->bbb->receive($this->attendance);
    }

    public function benchTheShareOfTwoHundredStays(): void
    {
        AttendanceSheet::percent($this->intervals, $this->start, $this->end);
    }
}
