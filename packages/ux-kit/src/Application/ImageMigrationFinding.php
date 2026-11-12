<?php

declare(strict_types=1);

namespace Phil\UxKit\Application;

/**
 * An `img` or a `picture` of a template, as `ImageMigration` read it: where it stands, what it is, the recipe proposed
 * in its place, none when it is left as it is, and the notes, what stands in its way or what the proposal changes.
 */
final readonly class ImageMigrationFinding
{
    /**
     * @param int          $line     The line of the template the element starts on, from 1
     * @param int          $offset   The byte of the template the element starts at, from 0
     * @param string       $original The element as the template writes it
     * @param string|null  $proposal The recipe proposed in its place, none when it is left as it is
     * @param list<string> $notes    What stands in the way, or what the proposal changes
     */
    public function __construct(
        public int $line,
        public int $offset,
        public string $original,
        public ?string $proposal,
        public array $notes = [],
    ) {
    }
}
