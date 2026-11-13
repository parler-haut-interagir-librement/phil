<?php

declare(strict_types=1);

namespace Phil\CommunityNotes\Event;

/**
 * A note was erased with the data of its author. It names the note only, which the store no longer has.
 */
final readonly class NoteErased
{
    public function __construct(
        public string $note,
    ) {
    }
}
