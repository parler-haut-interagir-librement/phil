<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom;

/**
 * What the server says of a room: running with participants, waiting for its first participant, or gone — ended, or
 * never opened.
 */
enum RoomState: string
{
    case Running = 'running';
    case Waiting = 'waiting';
    case Gone = 'gone';
}
