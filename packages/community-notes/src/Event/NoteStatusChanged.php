<?php

declare(strict_types=1);

namespace Phil\CommunityNotes\Event;

/**
 * The status of a note changed: published, unpublished, decided not helpful or back to needing ratings. It names the note only; a listener reads the rest from the store.
 */
final readonly class NoteStatusChanged
{
    public function __construct(
        public string $note,
    ) {
    }
}
