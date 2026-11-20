<?php

declare(strict_types=1);

namespace Phil\AiBundle\Domain\Journal;

use Phil\AiBundle\Domain\Model\JournalEntry;
use Phil\AiBundle\Domain\ValueObject\Tokens;

/**
 * What a subject's tasks added up to over a span: how many, how many failed, the tokens, the cost by currency, and
 * what became of their suggestions.
 */
final readonly class JournalTotals
{
    /**
     * @param array<string, int> $costs     micros by currency code
     * @param array<string, int> $decisions tasks that succeeded, by decision: `pending`, `accepted`, `edited`, `rejected`
     */
    public function __construct(
        public int $tasks,
        public int $failed,
        public Tokens $tokens,
        public array $costs,
        public array $decisions,
    ) {
    }

    /**
     * The totals from the sums a store computed, one row per outcome, decision and currency.
     *
     * @param iterable<array{outcome: string, decision: string, currency: string, tasks: int|numeric-string, input: int|numeric-string|null, output: int|numeric-string|null, cost: int|numeric-string|null}> $groups
     */
    public static function fromGroups(iterable $groups): self
    {
        $tasks = 0;
        $failed = 0;
        $input = 0;
        $output = 0;
        $costs = [];
        $decisions = array_fill_keys(array_column(Decision::cases(), 'value'), 0);

        foreach ($groups as $group) {
            $count = (int) $group['tasks'];
            $tasks += $count;
            $input += (int) $group['input'];
            $output += (int) $group['output'];
            $costs[$group['currency']] = ($costs[$group['currency']] ?? 0) + (int) $group['cost'];

            if (Outcome::Failed->value === $group['outcome']) {
                $failed += $count;
            } else {
                $decisions[Decision::from($group['decision'])->value] += $count;
            }
        }

        return new self($tasks, $failed, new Tokens($input, $output), $costs, $decisions);
    }

    public static function of(JournalEntry ...$entries): self
    {
        $failed = 0;
        $input = 0;
        $output = 0;
        $costs = [];
        $decisions = array_fill_keys(array_column(Decision::cases(), 'value'), 0);

        foreach ($entries as $entry) {
            $tokens = $entry->tokens();
            $cost = $entry->cost();
            $input += $tokens->input;
            $output += $tokens->output;
            $costs[$cost->currency] = ($costs[$cost->currency] ?? 0) + $cost->micros;

            if (Outcome::Failed === $entry->outcome()) {
                ++$failed;
            } else {
                ++$decisions[$entry->decision()->value];
            }
        }

        return new self(\count($entries), $failed, new Tokens($input, $output), $costs, $decisions);
    }
}
