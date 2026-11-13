<?php

declare(strict_types=1);

namespace Phil\Stockroom;

use Phil\Stockroom\Exception\InvalidStock;

/**
 * What an event takes out of the stockroom: its load lines, the same item from the same container counted once.
 */
final readonly class LoadList
{
    /** The most lines a load list holds. */
    public const int MAX_LINES = 500;

    /** @var list<LoadLine> */
    public array $lines;

    /** @var array<array-key, list<LoadLine>> the lines that name their container, by item */
    private array $named;

    /**
     * @param iterable<LoadLine> $lines
     *
     * @throws InvalidStock when the event is not an identifier, or the lines are too many or add up past the bound
     */
    public function __construct(
        public string $event,
        iterable $lines,
    ) {
        Identifier::check($event, 'event of a load list');
        $merged = [];

        foreach ($lines as $line) {
            $key = $line->item."\0".$line->from;
            $merged[$key] = isset($merged[$key]) ? new LoadLine($line->item, $merged[$key]->quantity + $line->quantity, $line->from) : $line;

            if (\count($merged) > self::MAX_LINES) {
                throw InvalidStock::because(\sprintf('A load list holds at most %d lines.', self::MAX_LINES));
            }
        }

        $this->lines = array_values($merged);
        $named = [];

        foreach ($this->lines as $line) {
            if (null !== $line->from) {
                $named[$line->item][] = $line;
            }
        }

        $this->named = $named;
    }

    /**
     * The lines of an item that name the container to take it from, in the list's order.
     *
     * @return list<LoadLine>
     */
    public function named(string $item): array
    {
        return $this->named[$item] ?? [];
    }

    /**
     * The pieces of each item the list takes, whatever container they come from.
     *
     * @return array<array-key, int> by item: an identifier of digits only is an integer key
     */
    public function totals(): array
    {
        $totals = [];

        foreach ($this->lines as $line) {
            $totals[$line->item] = ($totals[$line->item] ?? 0) + $line->quantity;
        }

        return $totals;
    }
}
