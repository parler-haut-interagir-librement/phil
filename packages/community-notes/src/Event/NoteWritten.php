<?php

declare(strict_types=1);

namespace Phil\CommunityNotes\Event;

/**
 * A note was written. It names the note only; a listener reads the rest from the store.
 */
final readonly class NoteWritten
{
    public function __construct(
        public string $note,
    ) {
    }
}
