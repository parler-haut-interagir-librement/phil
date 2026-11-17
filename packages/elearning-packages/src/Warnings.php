<?php

declare(strict_types=1);

namespace Phil\ElearningPackages;

/**
 * The notes of one reading, each said once, bounded so that a package of a thousand faulty items does not write a
 * thousand lines.
 */
final class Warnings
{
    public const int MAX = 100;

    /** @var array<string, string> each note, by itself */
    private array $notes = [];

    private int $dropped = 0;

    public function add(string $note): void
    {
        if (isset($this->notes[$note])) {
            return;
        }

        if (\count($this->notes) >= self::MAX) {
            ++$this->dropped;

            return;
        }

        $this->notes[$note] = $note;
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        $notes = array_values($this->notes);

        return 0 === $this->dropped ? $notes : [...$notes, \sprintf('%d more notes are left out.', $this->dropped)];
    }
}
