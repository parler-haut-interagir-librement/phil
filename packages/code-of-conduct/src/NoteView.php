<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct;

use DateTimeImmutable;

/**
 * A note of a report, opened.
 */
final readonly class NoteView
{
    public function __construct(
        public string $id,
        public string $author,
        public string $text,
        public DateTimeImmutable $writtenAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['id' => $this->id, 'author' => $this->author, 'text' => '[sensitive]'];
    }
}
