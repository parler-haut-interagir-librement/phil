<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Definition;

use Closure;

/**
 * An action on rows. The voter attribute is asked on each row at every call; the action asks for a confirmation unless
 * told otherwise. It is immutable: each method returns a new action.
 */
final readonly class Action
{
    /**
     * @param Closure(list<array<string, mixed>|object>): void $handler receives the rows, read again from the data source
     */
    private function __construct(
        public string $name,
        public string $label,
        public string $attribute,
        public Closure $handler,
        public bool $onRow = true,
        public bool $onSelection = false,
        public bool $confirms = true,
        public ?string $confirmation = null,
        public bool $destructive = false,
    ) {
    }

    /**
     * An action offered on each row.
     *
     * @param Closure(list<array<string, mixed>|object>): void $handler
     */
    public static function row(string $name, string $label, string $attribute, Closure $handler): self
    {
        return new self($name, $label, $attribute, $handler);
    }

    /**
     * An action offered on the selected rows only.
     *
     * @param Closure(list<array<string, mixed>|object>): void $handler
     */
    public static function bulk(string $name, string $label, string $attribute, Closure $handler): self
    {
        return new self($name, $label, $attribute, $handler, onRow: false, onSelection: true);
    }

    /**
     * Whether the action takes this many rows: a selection takes any number, a row's action one.
     */
    public function accepts(int $rows): bool
    {
        return 1 === $rows || $this->onSelection;
    }

    /**
     * Offers an action of the rows on the selected rows too.
     */
    public function alsoOnSelection(): self
    {
        return clone ($this, ['onSelection' => true]);
    }

    /**
     * The question asked before the action, a translation key of the table's domain.
     */
    public function confirmedBy(string $question): self
    {
        return clone ($this, ['confirmation' => $question]);
    }

    public function withoutConfirmation(): self
    {
        return clone ($this, ['confirms' => false]);
    }

    public function destructive(): self
    {
        return clone ($this, ['destructive' => true]);
    }
}
