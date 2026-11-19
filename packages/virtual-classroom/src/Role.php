<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom;

/**
 * What a participant may do in a room: a moderator runs it — mutes, shares, records, ends —, an attendee takes part.
 */
enum Role: string
{
    case Moderator = 'moderator';
    case Attendee = 'attendee';
}
