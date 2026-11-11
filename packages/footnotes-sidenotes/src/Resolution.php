<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes;

/**
 * The notes of a document, numbered in reading order, and what is wrong with them.
 */
final readonly class Resolution
{
    /** @var array<string, Note> */
    private array $byId;

    /**
     * @param list<Note>    $notes    the notes referenced, in the order of their first reference
     * @param list<Problem> $problems the problems: the blocks' first, then by kind
     */
    public function __construct(
        public array $notes = [],
        public array $problems = [],
    ) {
        $byId = [];

        foreach ($notes as $note) {
            $byId[$note->id] = $note;
        }

        $this->byId = $byId;
    }

    public function note(string $id): ?Note
    {
        return $this->byId[$id] ?? null;
    }

    /**
     * Whether nothing of the document is lost: only repeated references, if anything, are reported.
     */
    public function isClean(): bool
    {
        return [] === array_filter($this->problems, static fn (Problem $problem): bool => $problem->kind->isError());
    }

    /**
     * The problems of a kind, in their order.
     *
     * @return list<Problem>
     */
    public function problemsOf(ProblemKind $kind): array
    {
        return array_values(array_filter($this->problems, static fn (Problem $problem): bool => $kind === $problem->kind));
    }
}
