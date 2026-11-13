<?php

declare(strict_types=1);

namespace Phil\CommunityNotes\Exception;

use RuntimeException;

/**
 * An action the qualification or the state of the note refuses: a member not qualified, an author rating their own
 * note, a note withdrawn.
 */
final class NoteRefused extends RuntimeException implements CommunityNotesException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
