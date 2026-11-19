<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Application\Contract;

use DateTimeImmutable;
use Phil\VirtualClassroom\Attendance\Interval;
use Phil\VirtualClassroom\ParticipantId;
use Phil\VirtualClassroom\Recording;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroomBundle\Domain\Model\Room;

/**
 * Where the rooms, the stays of their participants and their recordings are kept. A stay is named by its room, its
 * participant and the moment they arrived: an arrival within a stay already known — the same arrival reported twice,
 * a second device — begins none, so that a notification delivered again changes nothing.
 */
interface ClassroomStore
{
    public function saveRoom(Room $room): void;

    public function room(RoomId $id): ?Room;

    /**
     * The room the owner names, the most recent when several.
     */
    public function roomOfOwner(string $owner): ?Room;

    /**
     * The rooms open on their server, the oldest first.
     *
     * @param positive-int $limit
     *
     * @return list<Room>
     */
    public function openRooms(int $limit): array;

    /**
     * Begins a stay; false when the participant is in the room already, or a stay of theirs covers this moment.
     */
    public function arrive(RoomId $room, ParticipantId $participant, DateTimeImmutable $at): bool;

    /**
     * Ends the participant's latest stay still open that began by then; false when they have none.
     */
    public function leave(RoomId $room, ParticipantId $participant, DateTimeImmutable $at): bool;

    /**
     * Ends every stay still open in the room; says how many.
     */
    public function leaveAll(RoomId $room, DateTimeImmutable $at): int;

    /**
     * The participants with a stay still open in the room.
     *
     * @return list<ParticipantId>
     */
    public function present(RoomId $room): array;

    /**
     * The stays in the room, by participant, each participant once.
     *
     * @return list<array{participant: ParticipantId, stays: list<Interval>}>
     */
    public function stays(RoomId $room): array;

    /**
     * Every stay of a participant, in every room, for an access request, the earliest first.
     *
     * @return list<array{room: string, interval: Interval}>
     */
    public function staysOf(ParticipantId $participant): array;

    /**
     * Deletes every stay of a participant; says how many.
     */
    public function forget(ParticipantId $participant): int;

    /**
     * Keeps a recording, or updates the one with the same identifier and format; says whether it was not known.
     */
    public function saveRecording(Recording $recording): bool;

    /**
     * @return list<Recording>
     */
    public function recordings(RoomId $room): array;
}
