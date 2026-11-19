<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom;

use Phil\Url\Url;
use Phil\VirtualClassroom\Exception\InvalidValue;

/**
 * What a room is opened with: its identifier, the title the participants see — 2 to 64 characters, the bounds of
 * BigBlueButton —, whether its session is recorded, the longest it may run in minutes, and the two addresses the
 * server calls back: `endedUrl` when the room ends, `eventsUrl` with the signed notifications — a recording ready,
 * the attendance of a session. A provider uses what it supports and ignores the rest.
 */
final readonly class RoomSpec
{
    public const int TITLE_MIN = 2;

    public const int TITLE_MAX = 64;

    public const int MAX_MINUTES = 1440;

    public string $title;

    /**
     * @param ?int $maxMinutes 1 to 1440, or null for the server's own limit
     *
     * @throws InvalidValue when the title or the duration is out of its bounds
     */
    public function __construct(
        public RoomId $id,
        string $title,
        public bool $record = false,
        public ?int $maxMinutes = null,
        public ?Url $endedUrl = null,
        public ?Url $eventsUrl = null,
    ) {
        $title = trim($title);
        $length = 1 === preg_match('//u', $title) ? mb_strlen($title) : 0;

        if ($length < self::TITLE_MIN || $length > self::TITLE_MAX || 1 === preg_match('/\p{Cc}/u', $title)) {
            throw InvalidValue::because(\sprintf('The title of a room is %d to %d characters of valid UTF-8, without control characters.', self::TITLE_MIN, self::TITLE_MAX));
        }

        if (null !== $maxMinutes && ($maxMinutes < 1 || $maxMinutes > self::MAX_MINUTES)) {
            throw InvalidValue::because(\sprintf('A room runs 1 to %d minutes, or as long as the server allows.', self::MAX_MINUTES));
        }

        $this->title = $title;
    }
}
