<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Domain\Model;

use DateTimeImmutable;
use Phil\VirtualClassroom\Exception\InvalidValue;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroom\RoomSpec;
use Phil\VirtualClassroomBundle\Domain\Exception\InvalidRoom;
use Phil\VirtualClassroomBundle\Domain\Status\RoomStatus;

/**
 * A room of the application: its identifier, the provider that hosts it, its title, whether it records, the longest
 * it runs, what owns it — an activity of a course, a meeting of a calendar, as the owner names it —, where it stands,
 * the session key the server gave when it opened, and when it was scheduled, opened and ended, in UTC.
 */
final class Room
{
    public const int PROVIDER_LENGTH = 64;

    public const int OWNER_LENGTH = 190;

    public const int SESSION_KEY_LENGTH = 32;

    public private(set) RoomStatus $status = RoomStatus::Scheduled;

    public private(set) ?string $sessionKey = null;

    public private(set) ?DateTimeImmutable $openedAt = null;

    public private(set) ?DateTimeImmutable $endedAt = null;

    public readonly string $title;

    /**
     * @throws InvalidRoom when a value is out of its bounds
     */
    public function __construct(
        public readonly RoomId $id,
        public readonly string $provider,
        string $title,
        public readonly bool $record,
        public readonly ?int $maxMinutes,
        public readonly ?string $owner,
        public readonly DateTimeImmutable $createdAt,
    ) {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/D', $provider)) {
            throw InvalidRoom::because('A provider is named by 1 to 64 lowercase letters, digits, "_", "." or "-".');
        }

        if (null !== $owner && 1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:\/-]{0,189}$/D', $owner)) {
            throw InvalidRoom::because('The owner of a room is 1 to 190 letters, digits, "_", ".", ":", "/" or "-".');
        }

        try {
            $this->title = new RoomSpec($id, $title, $record, $maxMinutes)->title;
        } catch (InvalidValue $invalidValue) {
            throw InvalidRoom::because($invalidValue->getMessage());
        }
    }

    /**
     * A room as the store keeps it.
     *
     * @throws InvalidRoom when a value is out of its bounds
     */
    public static function restore(
        RoomId $id,
        string $provider,
        string $title,
        bool $record,
        ?int $maxMinutes,
        ?string $owner,
        DateTimeImmutable $createdAt,
        RoomStatus $status,
        ?string $sessionKey,
        ?DateTimeImmutable $openedAt,
        ?DateTimeImmutable $endedAt,
    ): self {
        $room = new self($id, $provider, $title, $record, $maxMinutes, $owner, $createdAt);
        $room->status = $status;
        $room->sessionKey = $sessionKey;
        $room->openedAt = $openedAt;
        $room->endedAt = $endedAt;

        return $room;
    }

    /**
     * Notes the room open on the server, with the key of its session; says whether it was not open before.
     *
     * @throws InvalidRoom when the room has ended, or the key is longer than a key
     */
    public function open(?string $sessionKey, DateTimeImmutable $at): bool
    {
        if (RoomStatus::Ended === $this->status) {
            throw InvalidRoom::because('An ended room does not open again.');
        }

        if (null !== $sessionKey && \strlen($sessionKey) > self::SESSION_KEY_LENGTH) {
            throw InvalidRoom::because(\sprintf('The session key of a room is at most %d bytes.', self::SESSION_KEY_LENGTH));
        }

        $this->sessionKey = $sessionKey;

        if (RoomStatus::Open === $this->status) {
            return false;
        }

        $this->status = RoomStatus::Open;
        $this->openedAt = $at;

        return true;
    }

    /**
     * Ends the room; says whether it had not ended before.
     */
    public function end(DateTimeImmutable $at): bool
    {
        if (RoomStatus::Ended === $this->status) {
            return false;
        }

        $this->status = RoomStatus::Ended;
        $this->endedAt = $at;

        return true;
    }

    public function hasEnded(): bool
    {
        return RoomStatus::Ended === $this->status;
    }
}
