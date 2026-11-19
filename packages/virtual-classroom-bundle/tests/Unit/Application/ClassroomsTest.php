<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Tests\Unit\Application;

use Doctrine\DBAL\DriverManager;
use Phil\VirtualClassroom\Event\ParticipantJoined;
use Phil\VirtualClassroom\Event\ParticipantLeft;
use Phil\VirtualClassroom\Event\RecordingReady;
use Phil\VirtualClassroom\Event\RoomEnded;
use Phil\VirtualClassroom\Exception\InvalidWebhook;
use Phil\VirtualClassroom\Exception\ProviderUnavailable;
use Phil\VirtualClassroom\Participant;
use Phil\VirtualClassroom\ParticipantId;
use Phil\VirtualClassroom\Presences;
use Phil\VirtualClassroom\Recording;
use Phil\VirtualClassroom\Role;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroom\RoomState;
use Phil\VirtualClassroom\Webhook;
use Phil\VirtualClassroomBundle\Application\AttendeeReport;
use Phil\VirtualClassroomBundle\Application\Classrooms;
use Phil\VirtualClassroomBundle\Application\Providers;
use Phil\VirtualClassroomBundle\Domain\Event\AttendanceUpdated;
use Phil\VirtualClassroomBundle\Domain\Event\ClassroomEnded;
use Phil\VirtualClassroomBundle\Domain\Event\ClassroomOpened;
use Phil\VirtualClassroomBundle\Domain\Event\ParticipantArrived;
use Phil\VirtualClassroomBundle\Domain\Event\ParticipantDeparted;
use Phil\VirtualClassroomBundle\Domain\Event\RecordingAvailable;
use Phil\VirtualClassroomBundle\Domain\Exception\RoomClosed;
use Phil\VirtualClassroomBundle\Domain\Exception\UnknownProvider;
use Phil\VirtualClassroomBundle\Domain\Exception\UnknownRoom;
use Phil\VirtualClassroomBundle\Domain\Model\Room;
use Phil\VirtualClassroomBundle\Domain\Status\RoomStatus;
use Phil\VirtualClassroomBundle\Infrastructure\Dbal\DbalClassroomStore;
use Phil\VirtualClassroomBundle\Tests\Fixtures\FixedCallbacks;
use Phil\VirtualClassroomBundle\Tests\Fixtures\OtherEvent;
use Phil\VirtualClassroomBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ServiceLocator;

#[CoversClass(Classrooms::class)]
#[CoversClass(Providers::class)]
#[CoversClass(AttendeeReport::class)]
#[CoversClass(ClassroomOpened::class)]
#[CoversClass(ClassroomEnded::class)]
#[CoversClass(AttendanceUpdated::class)]
#[CoversClass(ParticipantArrived::class)]
#[CoversClass(ParticipantDeparted::class)]
#[CoversClass(RecordingAvailable::class)]
final class ClassroomsTest extends TestCase
{
    public function testARoomIsScheduledOnTheDefaultServer(): void
    {
        $stage = new Stage();

        $room = $stage->classrooms->schedule('  Algebra ', record: true, maxMinutes: 60, owner: 'course:1', id: RoomId::fromString('room1'));

        self::assertSame('room1', $room->id->value);
        self::assertSame('main', $room->provider);
        self::assertSame('Algebra', $room->title);
        self::assertTrue($room->record);
        self::assertSame(60, $room->maxMinutes);
        self::assertSame('course:1', $room->owner);
        self::assertSame(Stage::NOW, $room->createdAt->getTimestamp());
        self::assertSame('UTC', $room->createdAt->getTimezone()->getName());
        self::assertSame($room, $stage->store->room(RoomId::fromString('room1')));
        self::assertSame($room, $stage->classrooms->room(RoomId::fromString('room1')));
        self::assertSame($room, $stage->classrooms->ofOwner('course:1'));
        self::assertNull($stage->classrooms->ofOwner('course:2'));
        self::assertSame([], $stage->main->calls, 'Scheduling calls no server.');
    }

    public function testARoomWithoutIdentifierGetsADrawnOne(): void
    {
        $room = new Stage()->classrooms->schedule('Algebra', 'basic');

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $room->id->value);
        self::assertSame('basic', $room->provider);
        self::assertFalse($room->record);
        self::assertNull($room->maxMinutes);
        self::assertNull($room->owner);
    }

    public function testAnUnknownServerIsRefused(): void
    {
        $this->expectException(UnknownProvider::class);
        $this->expectExceptionMessageIsOrContains('No provider is named "other".');

        new Stage()->classrooms->schedule('Algebra', 'other');
    }

    public function testWithoutAServerNothingIsScheduled(): void
    {
        $classrooms = new Classrooms(new Stage()->store, new Providers(new ServiceLocator([]), [], null), new FixedCallbacks(), new Stage()->clock);

        $this->expectException(UnknownProvider::class);
        $this->expectExceptionMessageIsOrContains('No provider is named "(default)".');

        $classrooms->schedule('Algebra');
    }

    public function testAnUnknownRoom(): void
    {
        $this->expectException(UnknownRoom::class);
        $this->expectExceptionMessageIsOrContains('No room is named "room9".');

        new Stage()->classrooms->room(RoomId::fromString('room9'));
    }

    public function testTheFirstJoinOpensTheRoomWithItsCallbacks(): void
    {
        $stage = new Stage();
        $stage->classrooms->schedule('Algebra', record: true, maxMinutes: 45, id: RoomId::fromString('room1'));
        $stage->clock->sleep(30);

        $url = $stage->classrooms->joinUrl(RoomId::fromString('room1'), new Participant(ParticipantId::fromString('ada'), 'Ada'), Role::Moderator);

        self::assertSame('https://meet.example.com/room1?as=ada&role=moderator&session=session-1', $url);
        $spec = $stage->main->opened[0];
        self::assertSame('room1', $spec->id->value);
        self::assertSame('Algebra', $spec->title);
        self::assertTrue($spec->record);
        self::assertSame(45, $spec->maxMinutes);
        self::assertSame('https://app.example.com/ended/main/room1', $spec->endedUrl?->toString());
        self::assertSame('https://app.example.com/events/main/room1', $spec->eventsUrl?->toString());
        $room = $stage->classrooms->room(RoomId::fromString('room1'));
        self::assertSame(RoomStatus::Open, $room->status);
        self::assertSame('session-1', $room->sessionKey);
        self::assertSame(Stage::NOW + 30, $room->openedAt?->getTimestamp());
        self::assertSame(['ClassroomOpened room1'], $stage->events->names());

        $stage->main->sessionKey = 'session-2';
        $stage->clock->sleep(30);

        $again = $stage->classrooms->joinUrl(RoomId::fromString('room1'), new Participant(ParticipantId::fromString('bob'), 'Bob'), Role::Attendee);

        self::assertSame('https://meet.example.com/room1?as=bob&role=attendee&session=session-2', $again);
        self::assertSame(['open room1', 'open room1'], $stage->main->calls);
        self::assertSame(['ClassroomOpened room1'], $stage->events->names(), 'The room opened once.');
        self::assertSame(Stage::NOW + 30, $room->openedAt->getTimestamp());
        self::assertSame('session-2', $stage->store->room(RoomId::fromString('room1'))?->sessionKey);
    }

    public function testAnEndedRoomLetsNobodyIn(): void
    {
        $stage = new Stage();
        $stage->classrooms->schedule('Algebra', id: RoomId::fromString('room1'));
        $stage->classrooms->end(RoomId::fromString('room1'));

        $this->expectException(RoomClosed::class);
        $this->expectExceptionMessageIsOrContains('The room "room1" has ended.');

        $stage->classrooms->joinUrl(RoomId::fromString('room1'), new Participant(ParticipantId::fromString('ada'), 'Ada'), Role::Attendee);
    }

    public function testAServerOutOfReachLeavesTheRoomScheduled(): void
    {
        $stage = new Stage();
        $stage->classrooms->schedule('Algebra', id: RoomId::fromString('room1'));
        $stage->main->failure = ProviderUnavailable::because('Down.');

        try {
            $stage->classrooms->joinUrl(RoomId::fromString('room1'), new Participant(ParticipantId::fromString('ada'), 'Ada'), Role::Attendee);
            self::fail('The join succeeded.');
        } catch (ProviderUnavailable) {
            self::assertSame(RoomStatus::Scheduled, $stage->classrooms->room(RoomId::fromString('room1'))->status);
            self::assertSame([], $stage->events->events);
        }
    }

    public function testEndingAnOpenRoomEndsItOnItsServerAndClosesTheStays(): void
    {
        $stage = new Stage();
        $this->openRoom($stage);
        $stage->store->arrive(RoomId::fromString('room1'), ParticipantId::fromString('ada'), $stage->at(0));
        $stage->clock->sleep(600);

        $stage->classrooms->end(RoomId::fromString('room1'));
        $stage->classrooms->end(RoomId::fromString('room1'));

        self::assertSame(['open room1', 'end room1'], $stage->main->calls);
        $room = $stage->classrooms->room(RoomId::fromString('room1'));
        self::assertSame(RoomStatus::Ended, $room->status);
        self::assertSame(Stage::NOW + 600, $room->endedAt?->getTimestamp());
        self::assertSame(RoomStatus::Ended, $stage->store->room(RoomId::fromString('room1'))?->status);
        self::assertSame([], $stage->store->present(RoomId::fromString('room1')));
        self::assertSame(['ClassroomOpened room1', 'ClassroomEnded room1', 'AttendanceUpdated room1'], $stage->events->names());
    }

    public function testARoomNeverOpenedOrOnAServerThatCannotEndIsEndedHere(): void
    {
        $stage = new Stage();
        $stage->classrooms->schedule('Algebra', id: RoomId::fromString('room1'));
        $stage->classrooms->schedule('Algebra', 'basic', id: RoomId::fromString('room2'));
        $stage->classrooms->joinUrl(RoomId::fromString('room2'), new Participant(ParticipantId::fromString('ada'), 'Ada'), Role::Attendee);

        $stage->classrooms->end(RoomId::fromString('room1'));
        $stage->classrooms->end(RoomId::fromString('room2'));

        self::assertSame([], $stage->main->calls);
        self::assertSame(['open room2'], $stage->basic->calls);
        self::assertTrue($stage->classrooms->room(RoomId::fromString('room1'))->hasEnded());
        self::assertTrue($stage->classrooms->room(RoomId::fromString('room2'))->hasEnded());
    }

    public function testSyncingNotesArrivalsAndDepartures(): void
    {
        $stage = new Stage();
        $this->openRoom($stage);
        $stage->main->presences = new Presences(RoomState::Running, [ParticipantId::fromString('ada'), ParticipantId::fromString('bob')]);

        $stage->classrooms->sync(RoomId::fromString('room1'));
        $stage->clock->sleep(300);
        $stage->main->presences = new Presences(RoomState::Running, [ParticipantId::fromString('bob')]);
        $stage->classrooms->sync(RoomId::fromString('room1'));

        self::assertSame(['open room1', 'presences room1', 'presences room1'], $stage->main->calls, 'A room that does not record lists no recordings.');
        self::assertSame(['ClassroomOpened room1', 'ParticipantArrived room1 ada', 'ParticipantArrived room1 bob', 'ParticipantDeparted room1 ada'], $stage->events->names());
        $stays = $stage->store->stays(RoomId::fromString('room1'));
        self::assertSame('ada', $stays[0]['participant']->value);
        self::assertSame(Stage::NOW + 300, $stays[0]['stays'][0]->leftAt?->getTimestamp());
        self::assertNull($stays[1]['stays'][0]->leftAt);
    }

    public function testARoomWaitingForItsFirstParticipantClosesTheStaysStillOpen(): void
    {
        $stage = new Stage();
        $this->openRoom($stage);
        $stage->store->arrive(RoomId::fromString('room1'), ParticipantId::fromString('ada'), $stage->at(0));
        $stage->main->presences = new Presences(RoomState::Waiting);

        $stage->classrooms->sync(RoomId::fromString('room1'));

        self::assertSame([], $stage->store->present(RoomId::fromString('room1')));
        self::assertFalse($stage->classrooms->room(RoomId::fromString('room1'))->hasEnded());
    }

    public function testARoomTheServerNoLongerKnowsEnds(): void
    {
        $stage = new Stage();
        $this->openRoom($stage);
        $stage->main->presences = Presences::gone();
        $stage->clock->sleep(60);

        $stage->classrooms->sync(RoomId::fromString('room1'));

        $room = $stage->classrooms->room(RoomId::fromString('room1'));
        self::assertTrue($room->hasEnded());
        self::assertSame(Stage::NOW + 60, $room->endedAt?->getTimestamp());
        self::assertNotContains('end room1', $stage->main->calls);
    }

    public function testSyncingAsksNothingOfARoomNotOpenEndedOrOnAServerThatCannotSay(): void
    {
        $stage = new Stage();
        $stage->classrooms->schedule('Scheduled', id: RoomId::fromString('room1'));
        $stage->classrooms->schedule('Basic', 'basic', record: true, id: RoomId::fromString('room2'));
        $stage->classrooms->joinUrl(RoomId::fromString('room2'), new Participant(ParticipantId::fromString('ada'), 'Ada'), Role::Attendee);
        $this->openRoom($stage, 'room3');
        $stage->classrooms->end(RoomId::fromString('room3'));
        $stage->main->calls = [];
        $stage->basic->calls = [];

        $stage->classrooms->sync(RoomId::fromString('room1'));
        $stage->classrooms->sync(RoomId::fromString('room2'));
        $stage->classrooms->sync(RoomId::fromString('room3'));

        self::assertSame([], $stage->main->calls);
        self::assertSame([], $stage->basic->calls);
    }

    public function testSyncingARecordedRoomListsItsRecordings(): void
    {
        $stage = new Stage();
        $this->openRoom($stage, 'room1', true);
        $stage->classrooms->end(RoomId::fromString('room1'));
        $stage->main->recordings = [new Recording('rec-1', RoomId::fromString('room1'), 'video'), new Recording('rec-1', RoomId::fromString('room1'), 'presentation')];

        $stage->classrooms->sync(RoomId::fromString('room1'));
        $stage->classrooms->sync(RoomId::fromString('room1'));

        self::assertCount(2, $stage->classrooms->recordings(RoomId::fromString('room1')));
        self::assertSame(['RecordingAvailable room1 rec-1', 'RecordingAvailable room1 rec-1'], array_values(array_filter($stage->events->names(), static fn (string $name): bool => str_starts_with($name, 'Recording'))));
    }

    public function testNotificationsAreApplied(): void
    {
        $stage = new Stage();
        $this->openRoom($stage, 'room1', true);
        $room = RoomId::fromString('room1');
        $ada = ParticipantId::fromString('ada');
        $stage->main->events = [
            new ParticipantJoined($room, $ada, $stage->at(0)),
            new ParticipantJoined($room, $ada, $stage->at(5)),
            new ParticipantLeft($room, $ada, $stage->at(1800)),
            new RecordingReady($room, 'rec-2', $stage->at(3600), new Recording('rec-2', $room, 'download', 'https://example.com/r')),
            new ParticipantJoined(RoomId::fromString('unknown'), $ada, $stage->at(0)),
        ];

        self::assertSame(3, $stage->classrooms->receive('main', null, new Webhook('POST')));
        self::assertSame(0, $stage->classrooms->receive('main', null, new Webhook('POST')), 'A notification delivered twice changes nothing.');
        self::assertSame(['ClassroomOpened room1', 'ParticipantArrived room1 ada', 'ParticipantDeparted room1 ada', 'RecordingAvailable room1 rec-2'], $stage->events->names());
        self::assertSame('https://example.com/r', $stage->classrooms->recordings($room)[0]->url);
    }

    public function testARecordingAnnouncedWithoutDetailsIsListed(): void
    {
        $stage = new Stage();
        $this->openRoom($stage, 'room1', true);
        $stage->main->recordings = [new Recording('rec-3', RoomId::fromString('room1'), 'video')];
        $stage->main->events = [new RecordingReady(RoomId::fromString('room1'), 'rec-3', $stage->at(0))];

        self::assertSame(1, $stage->classrooms->receive('main', RoomId::fromString('room1'), new Webhook('POST')));
        self::assertSame(0, $stage->classrooms->receive('main', RoomId::fromString('room1'), new Webhook('POST')));
        self::assertSame('rec-3', $stage->classrooms->recordings(RoomId::fromString('room1'))[0]->id);
    }

    public function testARecordingAnnouncedWithoutDetailsByAServerThatCannotListIsIgnored(): void
    {
        $stage = new Stage();
        $stage->classrooms->schedule('Basic', 'basic', record: true, id: RoomId::fromString('room1'));
        $stage->basic->events = [new RecordingReady(RoomId::fromString('room1'), 'rec-3', $stage->at(0))];

        self::assertSame(0, $stage->classrooms->receive('basic', null, new Webhook('POST')));
        self::assertSame([], $stage->basic->calls);
    }

    public function testTheEventsOfARoomOfAnotherServerAreIgnored(): void
    {
        $stage = new Stage();
        $this->openRoom($stage);
        $stage->basic->events = [new RoomEnded(RoomId::fromString('room1'), $stage->at(0))];

        self::assertSame(0, $stage->classrooms->receive('basic', null, new Webhook('POST')));
        self::assertFalse($stage->classrooms->room(RoomId::fromString('room1'))->hasEnded());
    }

    public function testANotificationForAnotherRoomThanItsAddressIsRefused(): void
    {
        $stage = new Stage();
        $this->openRoom($stage);
        $stage->main->events = [new RoomEnded(RoomId::fromString('room1'), $stage->at(0))];

        $this->expectException(InvalidWebhook::class);
        $this->expectExceptionMessageIsOrContains('The notification names another room than its address.');

        $stage->classrooms->receive('main', RoomId::fromString('room2'), new Webhook('GET'));
    }

    public function testTheEndOfARoomByNotification(): void
    {
        $stage = new Stage();
        $this->openRoom($stage);
        $stage->store->arrive(RoomId::fromString('room1'), ParticipantId::fromString('ada'), $stage->at(0));
        $stage->main->events = [new RoomEnded(RoomId::fromString('room1'), $stage->at(900))];

        self::assertSame(1, $stage->classrooms->receive('main', RoomId::fromString('room1'), new Webhook('GET', [], [], '', Webhook::ENDED)));

        $room = $stage->classrooms->room(RoomId::fromString('room1'));
        self::assertSame(Stage::NOW + 900, $room->endedAt?->getTimestamp());
        self::assertSame(Stage::NOW + 900, $stage->store->stays(RoomId::fromString('room1'))[0]['stays'][0]->leftAt?->getTimestamp());
        self::assertSame(['ClassroomOpened room1', 'ClassroomEnded room1', 'AttendanceUpdated room1'], $stage->events->names());
        self::assertNotContains('end room1', $stage->main->calls);
    }

    public function testAStayReportedAfterTheEndUpdatesTheAttendanceAndEndsWithTheRoom(): void
    {
        $stage = new Stage();
        $this->openRoom($stage);
        $stage->clock->sleep(3600);
        $stage->classrooms->end(RoomId::fromString('room1'));
        $stage->events->events = [];

        $room = RoomId::fromString('room1');
        $stage->main->events = [
            new ParticipantJoined($room, ParticipantId::fromString('ada'), $stage->at(0)),
            new ParticipantJoined($room, ParticipantId::fromString('bob'), $stage->at(600)),
            new ParticipantLeft($room, ParticipantId::fromString('bob'), $stage->at(1200)),
        ];

        self::assertSame(3, $stage->classrooms->receive('main', null, new Webhook('POST')));

        self::assertSame(['ParticipantArrived room1 ada', 'ParticipantArrived room1 bob', 'ParticipantDeparted room1 bob', 'AttendanceUpdated room1'], $stage->events->names());
        self::assertSame([], $stage->store->present($room));
        self::assertSame(Stage::NOW + 3600, $stage->store->stays($room)[0]['stays'][0]->leftAt?->getTimestamp());

        $stage->events->events = [];
        self::assertSame(0, $stage->classrooms->receive('main', null, new Webhook('POST')));
        self::assertSame([], $stage->events->names(), 'Nothing new, no update.');
    }

    public function testARecordingAfterTheEndDoesNotUpdateTheAttendance(): void
    {
        $stage = new Stage();
        $this->openRoom($stage, 'room1', true);
        $stage->classrooms->end(RoomId::fromString('room1'));
        $stage->events->events = [];
        $stage->main->events = [new RecordingReady(RoomId::fromString('room1'), 'r', $stage->at(0), new Recording('r', RoomId::fromString('room1'), 'video'))];

        $stage->classrooms->receive('main', null, new Webhook('POST'));

        self::assertSame(['RecordingAvailable room1 r'], $stage->events->names());
    }

    public function testTheAttendanceOfARoom(): void
    {
        $stage = new Stage();
        $this->openRoom($stage);
        $room = RoomId::fromString('room1');
        $stage->store->arrive($room, ParticipantId::fromString('ada'), $stage->at(0));
        $stage->store->leave($room, ParticipantId::fromString('ada'), $stage->at(2700));
        $stage->store->arrive($room, ParticipantId::fromString('bob'), $stage->at(1800));
        $stage->clock->sleep(3600);

        $running = $stage->classrooms->attendance($room);
        self::assertSame([['ada', 2700, 75], ['bob', 1800, 50]], array_map(static fn (AttendeeReport $report): array => [$report->participant->value, $report->seconds, $report->percent], $running));
        self::assertCount(1, $running[0]->stays);

        $stage->clock->sleep(3600);
        $stage->classrooms->end($room);

        $ended = $stage->classrooms->attendance($room);
        self::assertSame([['ada', 2700, 37], ['bob', 5400, 75]], array_map(static fn (AttendeeReport $report): array => [$report->participant->value, $report->seconds, $report->percent], $ended));
    }

    public function testTheAttendanceOfARoomNeverOpenedHereStartsAtItsFirstArrival(): void
    {
        $stage = new Stage();
        $stage->classrooms->schedule('Algebra', id: RoomId::fromString('room1'));

        $room = RoomId::fromString('room1');
        $stage->store->arrive($room, ParticipantId::fromString('bob'), $stage->at(1200));
        $stage->store->leave($room, ParticipantId::fromString('bob'), $stage->at(1800));
        $stage->store->arrive($room, ParticipantId::fromString('ada'), $stage->at(600));
        $stage->store->leave($room, ParticipantId::fromString('ada'), $stage->at(1800));
        $stage->clock->sleep(1800);

        self::assertSame([['bob', 600, 50], ['ada', 1200, 100]], array_map(static fn (AttendeeReport $report): array => [$report->participant->value, $report->seconds, $report->percent], $stage->classrooms->attendance($room)));
    }

    public function testTheAttendanceOfAnEmptyRoomNeverOpened(): void
    {
        $stage = new Stage();
        $stage->classrooms->schedule('Algebra', id: RoomId::fromString('room1'));

        self::assertSame([], $stage->classrooms->attendance(RoomId::fromString('room1')));
    }

    public function testTheAttendanceStartsAtTheOpeningNotAtAnEarlierArrival(): void
    {
        $stage = new Stage();
        $this->openRoom($stage);
        $room = RoomId::fromString('room1');
        $stage->store->arrive($room, ParticipantId::fromString('ada'), $stage->at(-600));
        $stage->store->leave($room, ParticipantId::fromString('ada'), $stage->at(1200));
        $stage->clock->sleep(1800);

        self::assertSame([['ada', 1200, 66]], array_map(static fn (AttendeeReport $report): array => [$report->participant->value, $report->seconds, $report->percent], $stage->classrooms->attendance($room)));
    }

    public function testTheAttendanceOfAnEndedRoomDoesNotGrowWithTime(): void
    {
        $stage = new Stage();
        $this->openRoom($stage);
        $room = RoomId::fromString('room1');
        $stage->store->arrive($room, ParticipantId::fromString('ada'), $stage->at(0));
        $stage->clock->sleep(600);
        $stage->classrooms->end($room);
        $stage->clock->sleep(3600);

        self::assertSame([['ada', 600, 100]], array_map(static fn (AttendeeReport $report): array => [$report->participant->value, $report->seconds, $report->percent], $stage->classrooms->attendance($room)));
    }

    public function testAnEarlyArrivalIsTheFirstOfTheRoomWhateverTheOrderOfTheParticipants(): void
    {
        $stage = new Stage();
        $stage->classrooms->schedule('Algebra', id: RoomId::fromString('room1'));

        $room = RoomId::fromString('room1');
        $stage->store->arrive($room, ParticipantId::fromString('ada'), $stage->at(600));
        $stage->store->leave($room, ParticipantId::fromString('ada'), $stage->at(1800));
        $stage->store->arrive($room, ParticipantId::fromString('bob'), $stage->at(1200));
        $stage->store->leave($room, ParticipantId::fromString('bob'), $stage->at(1800));
        $stage->clock->sleep(1800);

        self::assertSame([['ada', 1200, 100], ['bob', 600, 50]], array_map(static fn (AttendeeReport $report): array => [$report->participant->value, $report->seconds, $report->percent], $stage->classrooms->attendance($room)));
    }

    public function testWithoutADispatcherEverythingHappensInSilence(): void
    {
        $stage = new Stage(false);
        $this->openRoom($stage, 'room1', true);
        $room = RoomId::fromString('room1');
        $ada = ParticipantId::fromString('ada');
        $stage->main->presences = new Presences(RoomState::Running, [$ada]);
        $stage->main->recordings = [new Recording('rec-1', $room, 'video')];

        $stage->classrooms->sync($room);
        $stage->main->presences = new Presences(RoomState::Running);
        $stage->classrooms->sync($room);
        $stage->classrooms->end($room);
        $stage->main->events = [new ParticipantJoined($room, ParticipantId::fromString('bob'), $stage->at(0))];

        self::assertSame(1, $stage->classrooms->receive('main', null, new Webhook('POST')));
        self::assertTrue($stage->classrooms->room($room)->hasEnded());
        self::assertSame([], $stage->events->events);
        self::assertCount(1, $stage->classrooms->recordings($room));
    }

    public function testTheRoomIsSavedWhenItOpensAndWhenItEnds(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $store = new DbalClassroomStore($connection);
        $manager = $connection->createSchemaManager();
        $manager->migrateSchema($store->configureSchema($manager->introspectSchema()));

        $stage = new Stage(true, $store);
        $room = RoomId::fromString('room1');
        $stage->classrooms->schedule('Algebra', id: $room);

        $stage->classrooms->joinUrl($room, new Participant(ParticipantId::fromString('ada'), 'Ada'), Role::Attendee);

        $opened = $store->room($room);
        self::assertNotNull($opened);
        self::assertSame(RoomStatus::Open, $opened->status);
        self::assertSame('session-1', $opened->sessionKey);

        $stage->classrooms->end($room);

        $ended = $store->room($room);
        self::assertNotNull($ended);
        self::assertSame(RoomStatus::Ended, $ended->status);
    }

    public function testAnEventAfterTheOneOfAnUnknownRoomIsApplied(): void
    {
        $stage = new Stage();
        $this->openRoom($stage);
        $room = RoomId::fromString('room1');
        $stage->main->events = [
            new ParticipantJoined(RoomId::fromString('unknown'), ParticipantId::fromString('ada'), $stage->at(0)),
            new ParticipantJoined($room, ParticipantId::fromString('ada'), $stage->at(0)),
        ];

        self::assertSame(1, $stage->classrooms->receive('main', null, new Webhook('POST')));
        self::assertSame(['ada'], array_map(static fn (ParticipantId $participant): string => $participant->value, $stage->store->present($room)));
    }

    public function testAnEventOfAKindTheServiceDoesNotKnowChangesNothing(): void
    {
        $stage = new Stage();
        $this->openRoom($stage);
        $stage->main->events = [new OtherEvent(RoomId::fromString('room1'), $stage->at(0))];

        self::assertSame(0, $stage->classrooms->receive('main', null, new Webhook('POST')));
    }

    public function testTheAttendanceOfARoomThatIsNotEndedIsNotUpdatedByNotifications(): void
    {
        $stage = new Stage();
        $room = RoomId::fromString('room1');
        $stage->store->saveRoom(Room::restore($room, 'main', 'Algebra', false, null, null, $stage->at(0), RoomStatus::Open, 'session-1', $stage->at(0), $stage->at(10)));
        $stage->main->events = [new ParticipantJoined($room, ParticipantId::fromString('ada'), $stage->at(0))];

        self::assertSame(1, $stage->classrooms->receive('main', null, new Webhook('POST')));
        self::assertSame(['ada'], array_map(static fn (ParticipantId $participant): string => $participant->value, $stage->store->present($room)));
        self::assertNotContains('AttendanceUpdated room1', $stage->events->names());
    }

    public function testTheAttendanceOfAnEndedRoomWithoutMomentIsLeftAlone(): void
    {
        $stage = new Stage();
        $room = RoomId::fromString('room1');
        $stage->store->saveRoom(Room::restore($room, 'main', 'Algebra', false, null, null, $stage->at(0), RoomStatus::Ended, null, null, null));
        $stage->main->events = [new ParticipantJoined($room, ParticipantId::fromString('ada'), $stage->at(0))];

        self::assertSame(1, $stage->classrooms->receive('main', null, new Webhook('POST')));
        self::assertSame(['ParticipantArrived room1 ada'], $stage->events->names());
    }

    public function testTheServersByName(): void
    {
        $providers = new Stage()->providers;

        self::assertSame(['main', 'basic'], $providers->names());
        self::assertSame('main', $providers->defaultName());
        self::assertTrue($providers->has('basic'));
        self::assertFalse($providers->has('other'));
        self::assertFalse($providers->get('basic')->capabilities()->end);

        $this->expectException(UnknownProvider::class);
        $this->expectExceptionMessageIsOrContains('No provider is named "other".');

        $providers->get('other');
    }

    public function testAServiceThatIsNotAServerIsNone(): void
    {
        $providers = new Providers(new ServiceLocator(['main' => static fn (): object => new stdClass()]), ['main'], 'main');

        $this->expectException(UnknownProvider::class);

        $providers->get('main');
    }

    public function testANameOutsideTheListIsNotAskedOfTheContainer(): void
    {
        $providers = new Providers(new ServiceLocator(['hidden' => static fn (): object => new Stage()->main]), [], null);

        $this->expectException(UnknownProvider::class);

        $providers->get('hidden');
    }

    private function openRoom(Stage $stage, string $id = 'room1', bool $record = false): void
    {
        $stage->classrooms->schedule('Algebra', record: $record, id: RoomId::fromString($id));
        $stage->classrooms->joinUrl(RoomId::fromString($id), new Participant(ParticipantId::fromString('host'), 'Host'), Role::Moderator);
    }
}
