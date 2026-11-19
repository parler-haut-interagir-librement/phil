<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application;

use InvalidArgumentException;

/**
 * How many occurrences, and how many repeating events, the calendar unrolls for one request — its views, its feeds, a
 * member's calendar and the printed months alike —, shared by every call to `Calendar` until `reset()`. Each repeating
 * event read costs one expansion, whatever it gives: a rule is walked up to the bounds of `phil/recurrence` even when no
 * occurrence falls in the period. Past either limit, `Calendar` stops reading events and gives what it has.
 *
 * The bundle registers one, reset between the requests of a worker by `kernel.reset`. Code that calls the calendar in a
 * loop of its own — a console command — resets it between its units of work.
 */
final class ExpansionBudget
{
    /** The occurrences unrolled at most by default. */
    public const int OCCURRENCES = 10_000;

    /** The repeating events read at most by default. */
    public const int EXPANSIONS = 500;

    /** The highest limit either can be given. */
    public const int CEILING = 1_000_000;

    private int $occurrences = 0;

    private int $expansions = 0;

    /** Whether the last `limit()` asked for fewer occurrences than wanted. */
    private bool $reduced = false;

    private bool $exhausted = false;

    private bool $reported = false;

    public function __construct(
        public readonly int $maxOccurrences = self::OCCURRENCES,
        public readonly int $maxExpansions = self::EXPANSIONS,
    ) {
        foreach (['occurrences' => $maxOccurrences, 'expansions' => $maxExpansions] as $name => $limit) {
            if ($limit < 1 || $limit > self::CEILING) {
                throw new InvalidArgumentException(\sprintf('The budget of %s is 1 to %d.', $name, self::CEILING));
            }
        }
    }

    /**
     * Whether one more event can be read: any while occurrences are left, a repeating one while expansions are left
     * too. A refusal exhausts the budget, and every later call is refused until `reset()`.
     *
     * @param bool $repeats whether the event repeats, by a rule or by dates added
     */
    public function admits(bool $repeats): bool
    {
        if ($this->exhausted) {
            return false;
        }

        if ($this->occurrences >= $this->maxOccurrences || ($repeats && $this->expansions >= $this->maxExpansions)) {
            $this->exhausted = true;

            return false;
        }

        if ($repeats) {
            ++$this->expansions;
        }

        return true;
    }

    /**
     * How many occurrences an event can give: those wanted, or those left when fewer are.
     */
    public function limit(int $wanted): int
    {
        $left = $this->maxOccurrences - $this->occurrences;
        $this->reduced = $left < $wanted;

        return $this->reduced ? $left : $wanted;
    }

    /**
     * Counts the occurrences an event gave; an event that filled what `limit()` left exhausts the budget, since it may
     * have had more.
     */
    public function spend(int $occurrences): void
    {
        $this->occurrences += $occurrences;

        if ($this->reduced && $this->occurrences >= $this->maxOccurrences) {
            $this->exhausted = true;
        }
    }

    /**
     * Whether an event was left out, or cut short, since the last `reset()`.
     */
    public function isExhausted(): bool
    {
        return $this->exhausted;
    }

    /**
     * True the first time it is asked once the budget is exhausted, for the stop to be logged once a request.
     */
    public function shouldReport(): bool
    {
        if (!$this->exhausted || $this->reported) {
            return false;
        }

        $this->reported = true;

        return true;
    }

    public function occurrences(): int
    {
        return $this->occurrences;
    }

    public function expansions(): int
    {
        return $this->expansions;
    }

    /**
     * Back to nothing spent: between two requests, or two units of work of a command.
     */
    public function reset(): void
    {
        $this->occurrences = 0;
        $this->expansions = 0;
        $this->reduced = false;
        $this->exhausted = false;
        $this->reported = false;
    }
}
