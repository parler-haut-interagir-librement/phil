<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Infrastructure\Memory;

use DateTimeImmutable;
use Override;
use Phil\VirtualClassroom\Attendance\Interval;
use Phil\VirtualClassroom\ParticipantId;
use Phil\VirtualClassroom\Recording;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroomBundle\Application\Contract\ClassroomStore;
use Phil\VirtualClassroomBundle\Domain\Model\Room;
use Phil\VirtualClassroomBundle\Domain\Status\RoomStatus;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The rooms, stays and recordings in memory, for the tests of an application; emptied between requests.
 */
final class InMemoryClassroomStore implements ClassroomStore, ResetInterface
{
    /** @var array<string, Room> */
    private array $rooms = [];

    /** @var array<string, array<string, array{participant: ParticipantId, stays: list<Interval>}>> the stays by room, then by participant, each key prefixed so that it stays a string */
    private array $stays = [];

    /** @var array<string, array<string, Recording>> the recordings by room, then by identifier and format */
    private array $recordings = [];

    #[Override]
    public function saveRoom(Room $room): void
    {
        $this->rooms[$room->id->value] = $room;
    }

    #[Override]
    public function room(RoomId $id): ?Room
    {
        return $this->rooms[$id->value] ?? null;
    }

    #[Override]
    public function roomOfOwner(string $owner): ?Room
    {
        $found = null;

        foreach ($this->rooms as $room) {
            if ($room->owner === $owner && (null === $found || $room->createdAt >= $found->createdAt)) {
                $found = $room;
            }
        }

        return $found;
    }

    #[Override]
    public function openRooms(int $limit): array
    {
        $open = array_filter($this->rooms, static fn (Room $room): bool => RoomStatus::Open === $room->status);
        usort($open, static fn (Room $a, Room $b): int => $a->createdAt <=> $b->createdAt);

        return \array_slice($open, 0, $limit);
    }

    #[Override]
    public function arrive(RoomId $room, ParticipantId $participant, DateTimeImmutable $at): bool
    {
        $stays = $this->stays['r'.$room->value]['p'.$participant->value]['stays'] ?? [];

        foreach ($stays as $stay) {
            if (null === $stay->leftAt || ($stay->joinedAt <= $at && $at <= $stay->leftAt)) {
                return false;
            }
        }

        $stays[] = new Interval($at);
        usort($stays, static fn (Interval $a, Interval $b): int => $a->joinedAt <=> $b->joinedAt);
        $this->stays['r'.$room->value]['p'.$participant->value] = ['participant' => $participant, 'stays' => $stays];

        return true;
    }

    #[Override]
    public function leave(RoomId $room, ParticipantId $participant, DateTimeImmutable $at): bool
    {
        $stays = $this->stays['r'.$room->value]['p'.$participant->value]['stays'] ?? [];

        for ($index = \count($stays) - 1; $index >= 0; --$index) {
            $stay = $stays[$index];

            if (null === $stay->leftAt && $stay->joinedAt <= $at) {
                array_splice($stays, $index, 1, [new Interval($stay->joinedAt, $at)]);
                $this->stays['r'.$room->value]['p'.$participant->value] = ['participant' => $participant, 'stays' => $stays];

                return true;
            }
        }

        return false;
    }

    #[Override]
    public function leaveAll(RoomId $room, DateTimeImmutable $at): int
    {
        $closed = 0;

        foreach ($this->stays['r'.$room->value] ?? [] as $key => $entry) {
            $stays = $entry['stays'];

            foreach ($stays as $index => $stay) {
                if (null === $stay->leftAt) {
                    $stays[$index] = new Interval($stay->joinedAt, $at);
                    ++$closed;
                }
            }

            $this->stays['r'.$room->value][$key] = ['participant' => $entry['participant'], 'stays' => $stays];
        }

        return $closed;
    }

    #[Override]
    public function present(RoomId $room): array
    {
        $present = [];

        foreach ($this->stays['r'.$room->value] ?? [] as $entry) {
            foreach ($entry['stays'] as $stay) {
                if (null === $stay->leftAt) {
                    $present[] = $entry['participant'];

                    break;
                }
            }
        }

        return $present;
    }

    #[Override]
    public function stays(RoomId $room): array
    {
        return array_values($this->stays['r'.$room->value] ?? []);
    }

    #[Override]
    public function staysOf(ParticipantId $participant): array
    {
        $found = [];

        foreach ($this->stays as $room => $participants) {
            foreach ($participants['p'.$participant->value]['stays'] ?? [] as $stay) {
                $found[] = ['room' => substr($room, 1), 'interval' => $stay];
            }
        }

        usort($found, static fn (array $a, array $b): int => [$a['interval']->joinedAt, $a['room']] <=> [$b['interval']->joinedAt, $b['room']]);

        return $found;
    }

    #[Override]
    public function forget(ParticipantId $participant): int
    {
        $forgotten = 0;

        foreach ($this->stays as $room => $participants) {
            $forgotten += \count($participants['p'.$participant->value]['stays'] ?? []);
            unset($this->stays[$room]['p'.$participant->value]);
        }

        return $forgotten;
    }

    #[Override]
    public function saveRecording(Recording $recording): bool
    {
        $key = $recording->id."\0".$recording->format;
        $known = isset($this->recordings[$recording->room->value][$key]);
        $this->recordings[$recording->room->value][$key] = $recording;

        return !$known;
    }

    #[Override]
    public function recordings(RoomId $room): array
    {
        return array_values($this->recordings[$room->value] ?? []);
    }

    #[Override]
    public function reset(): void
    {
        $this->rooms = [];
        $this->stays = [];
        $this->recordings = [];
    }
}
