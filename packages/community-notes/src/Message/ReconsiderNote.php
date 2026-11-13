<?php

declare(strict_types=1);

namespace Phil\CommunityNotes\Message;

/**
 * Asks for a note to be decided again — after a change of the rule, or the erasure of a member whose ratings made the
 * history of others —, for a queue such as Symfony Messenger. It names the note only.
 */
final readonly class ReconsiderNote
{
    public function __construct(
        public string $note,
    ) {
    }
}
