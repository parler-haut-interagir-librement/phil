<?php

declare(strict_types=1);

namespace Phil\CommunityNotes\Message;

use Phil\CommunityNotes\CommunityNotes;
use Phil\CommunityNotes\Note;

/**
 * Decides again the note a `ReconsiderNote` names; a note erased in the meantime is skipped. An application registers
 * it as the handler of the message on its bus.
 */
final readonly class ReconsiderNoteHandler
{
    public function __construct(
        private CommunityNotes $notes,
    ) {
    }

    public function __invoke(ReconsiderNote $message): void
    {
        if ($this->notes->find($message->note) instanceof Note) {
            $this->notes->reconsider($message->note);
        }
    }
}
