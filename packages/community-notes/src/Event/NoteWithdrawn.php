<?php

declare(strict_types=1);

namespace Phil\CommunityNotes\Event;

/**
 * A note was withdrawn by its author. It names the note only; a listener reads the rest from the store.
 */
final readonly class NoteWithdrawn
{
    public function __construct(
        public string $note,
    ) {
    }
}
