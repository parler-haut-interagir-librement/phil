<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Tests\Benchmark;

use Phil\VirtualClassroom\Event\ParticipantJoined;
use Phil\VirtualClassroom\Event\ParticipantLeft;
use Phil\VirtualClassroom\ParticipantId;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroom\Webhook;
use Phil\VirtualClassroomBundle\Tests\Fixtures\Stage;

/**
 * The hot paths of a large class: a notification of 300 participants joining and leaving applied to the stays, and the
 * report of the attendance of the session, from a store in memory, as the store of the tests keeps it.
 */
final readonly class AttendanceBench
{
    private Stage $stage;

    public function __construct()
    {
        $this->stage = new Stage();
        $this->stage->classrooms->schedule('Large class', id: RoomId::fromString('large'));
        $this->stage->classrooms->schedule('Notified class', id: RoomId::fromString('notified'));

        $room = RoomId::fromString('large');

        for ($i = 0; $i < 300; ++$i) {
            $participant = ParticipantId::fromString('user-'.$i);
            $this->stage->store->arrive($room, $participant, $this->stage->at($i));
            $this->stage->store->leave($room, $participant, $this->stage->at(1800 + $i));
            $this->stage->store->arrive($room, $participant, $this->stage->at(2400 + $i));
        }

        $this->stage->clock->sleep(3600);
    }

    public function benchANotificationOfSixHundredEvents(): void
    {
        $room = RoomId::fromString('notified');
        $this->stage->main->events = [];

        for ($i = 0; $i < 300; ++$i) {
            $participant = ParticipantId::fromString('user-'.$i);
            $this->stage->main->events[] = new ParticipantJoined($room, $participant, $this->stage->at($i));
            $this->stage->main->events[] = new ParticipantLeft($room, $participant, $this->stage->at(600 + $i));
        }

        $this->stage->classrooms->receive('main', $room, new Webhook('POST'));
    }

    public function benchTheAttendanceOfThreeHundredParticipants(): void
    {
        $this->stage->classrooms->attendance(RoomId::fromString('large'));
    }
}
