<?php

declare(strict_types=1);

namespace Phil\CommunityNotes\Contract;

use Phil\CommunityNotes\Note;
use Phil\Resource\ResourceReference;

/**
 * Where notes are kept.
 */
interface NoteRepository
{
    public function get(string $id): ?Note;

    /**
     * The notes of a resource, the oldest first.
     *
     * @return list<Note>
     */
    public function ofSubject(ResourceReference $subject): array;

    /**
     * The notes a member wrote, the oldest first: their part of an access or erasure request.
     *
     * @return list<Note>
     */
    public function byAuthor(ResourceReference $author): array;

    /**
     * Keeps a new note, or the new state of one.
     */
    public function save(Note $note): void;

    /**
     * Forgets a note; nothing happens for a note the store does not have.
     */
    public function remove(string $id): void;
}
