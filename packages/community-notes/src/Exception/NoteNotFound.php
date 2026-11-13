<?php

declare(strict_types=1);

namespace Phil\CommunityNotes\Exception;

use RuntimeException;

/**
 * No note has this identifier.
 */
final class NoteNotFound extends RuntimeException implements CommunityNotesException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('No note is identified by "%s".', $id));
    }
}
