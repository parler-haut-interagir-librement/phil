<?php

declare(strict_types=1);

namespace Phil\CommunityNotes\Memory;

use Override;
use Phil\CommunityNotes\Contract\NoteRepository;
use Phil\CommunityNotes\Note;
use Phil\Resource\ResourceReference;

/**
 * The notes in memory, for tests and demonstrations; they last as long as the object.
 */
final class InMemoryNoteRepository implements NoteRepository
{
    /** @var array<string, Note> */
    private array $notes = [];

    #[Override]
    public function get(string $id): ?Note
    {
        return $this->notes[$id] ?? null;
    }

    #[Override]
    public function ofSubject(ResourceReference $subject): array
    {
        return $this->oldestFirst(static fn (Note $note): bool => $note->subject->equals($subject));
    }

    #[Override]
    public function byAuthor(ResourceReference $author): array
    {
        return $this->oldestFirst(static fn (Note $note): bool => $note->author->equals($author));
    }

    #[Override]
    public function save(Note $note): void
    {
        $this->notes[$note->id] = $note;
    }

    #[Override]
    public function remove(string $id): void
    {
        unset($this->notes[$id]);
    }

    /**
     * @param callable(Note): bool $kept
     *
     * @return list<Note>
     */
    private function oldestFirst(callable $kept): array
    {
        $notes = array_filter($this->notes, $kept);
        usort($notes, static fn (Note $a, Note $b): int => [$a->writtenAt->getTimestamp(), $a->id] <=> [$b->writtenAt->getTimestamp(), $b->id]);

        return $notes;
    }
}
