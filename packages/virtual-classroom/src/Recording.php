<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom;

use DateTimeImmutable;
use Phil\VirtualClassroom\Exception\InvalidValue;

/**
 * A recording of a room: the server's identifier of it, its format — `presentation`, `video`, `download` —, the
 * address it plays or downloads from when the server gives one, whether it is published, when the recorded session
 * started and ended, and until when the address works when it expires.
 */
final readonly class Recording
{
    public const int ID_LENGTH = 190;

    /**
     * @throws InvalidValue when the identifier or the format is not one a recording holds
     */
    public function __construct(
        public string $id,
        public RoomId $room,
        public string $format,
        public ?string $url = null,
        public bool $published = true,
        public ?DateTimeImmutable $startedAt = null,
        public ?DateTimeImmutable $endedAt = null,
        public ?DateTimeImmutable $expiresAt = null,
    ) {
        if (1 !== preg_match('/^[A-Za-z0-9._:-]{1,190}$/D', $id)) {
            throw InvalidValue::because('A recording is named by 1 to 190 letters, digits, ".", "_", ":" or "-".');
        }

        if (1 !== preg_match('/^[a-z0-9_-]{1,32}$/D', $format)) {
            throw InvalidValue::because('The format of a recording is 1 to 32 lowercase letters, digits, "-" or "_".');
        }
    }
}
