<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom;

use Phil\VirtualClassroom\Exception\InvalidValue;

/**
 * Who joins a room: their identifier, and the name the other participants see, 1 to 128 characters without control
 * characters. The name goes to the conferencing server in the join link and nowhere else: the library keeps it in no
 * log and no exception.
 */
final readonly class Participant
{
    public const int NAME_LENGTH = 128;

    public string $displayName;

    /**
     * @throws InvalidValue when the name is empty, too long or holds a control character
     */
    public function __construct(
        public ParticipantId $id,
        string $displayName,
    ) {
        $name = trim($displayName);

        if ('' === $name || 1 !== preg_match('//u', $name) || mb_strlen($name) > self::NAME_LENGTH || 1 === preg_match('/\p{Cc}/u', $name)) {
            throw InvalidValue::because(\sprintf('The name of a participant is 1 to %d characters of valid UTF-8, without control characters.', self::NAME_LENGTH));
        }

        $this->displayName = $name;
    }
}
