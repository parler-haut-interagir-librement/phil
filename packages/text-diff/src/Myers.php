<?php

declare(strict_types=1);

namespace Phil\TextDiff;

/**
 * Eugene W. Myers' O(ND) difference algorithm (1986) over two lists of tokens: the shortest script of deletions and
 * insertions, found by following the furthest-reaching path on each diagonal for 0, 1, 2… edits, then walking back
 * through the saved frontiers.
 *
 * The tokens both lists start and end with are set aside first; the search then runs on what is left, and stops at
 * `$maxEdits`: past that bound, what is left is given as deleted then inserted whole, which is what a reader makes of a
 * text rewritten from end to end anyway, and keeps time and memory bounded.
 *
 * @internal
 */
final readonly class Myers
{
    public function __construct(
        private int $maxEdits,
    ) {
    }

    /**
     * @param list<string> $old
     * @param list<string> $new
     *
     * @return list<Operation>
     */
    public function diff(array $old, array $new): array
    {
        $start = 0;
        $end = min(\count($old), \count($new));

        while ($start < $end && $old[$start] === $new[$start]) {
            ++$start;
        }

        $tail = 0;

        while ($tail < $end - $start && $old[\count($old) - 1 - $tail] === $new[\count($new) - 1 - $tail]) {
            ++$tail;
        }

        $edits = [
            ...self::same(\array_slice($old, 0, $start)),
            ...$this->search(\array_slice($old, $start, \count($old) - $start - $tail), \array_slice($new, $start, \count($new) - $start - $tail)),
            ...self::same(\array_slice($old, \count($old) - $tail)),
        ];

        return self::group(self::spaces(self::group($edits)));
    }

    /**
     * @param list<string> $old
     * @param list<string> $new
     *
     * @return list<array{OperationKind, string}>
     */
    private function search(array $old, array $new): array
    {
        $n = \count($old);
        $m = \count($new);
        $frontier = [1 => 0];
        $trace = [];

        for ($d = 0; $d <= $this->maxEdits; ++$d) {
            $trace[] = $frontier;

            for ($k = -$d; $k <= $d; $k += 2) {
                $x = self::start($frontier, $k, $d);
                $y = $x - $k;

                while ($x < $n && $y < $m && $old[$x] === $new[$y]) {
                    ++$x;
                    ++$y;
                }

                $frontier[$k] = $x;

                if ($x >= $n && $y >= $m) {
                    return self::walkBack($trace, $old, $new);
                }
            }
        }

        return [...self::all(OperationKind::Delete, $old), ...self::all(OperationKind::Insert, $new)];
    }

    /**
     * Where the path on diagonal `$k` starts with `$d` edits: below the end of the path on `$k + 1`, or right of the
     * end of the path on `$k - 1`.
     *
     * @param array<int, int> $frontier
     */
    private static function start(array $frontier, int $k, int $d): int
    {
        return self::down($frontier, $k, $d) ? $frontier[$k + 1] : $frontier[$k - 1] + 1;
    }

    /**
     * Whether the path to diagonal `$k` comes down from diagonal `$k + 1`, an insertion, rather than across from
     * `$k - 1`, a deletion.
     *
     * @param array<int, int> $frontier
     */
    private static function down(array $frontier, int $k, int $d): bool
    {
        return $k === -$d || ($k !== $d && $frontier[$k - 1] < $frontier[$k + 1]);
    }

    /**
     * @param list<array<int, int>> $trace
     * @param list<string>          $old
     * @param list<string>          $new
     *
     * @return list<array{OperationKind, string}>
     */
    private static function walkBack(array $trace, array $old, array $new): array
    {
        $x = \count($old);
        $y = \count($new);
        $edits = [];

        for ($d = \count($trace) - 1; $d > 0; --$d) {
            $frontier = $trace[$d];
            $k = $x - $y;
            $previous = self::down($frontier, $k, $d) ? $k + 1 : $k - 1;
            $previousX = $frontier[$previous];

            while ($x > $previousX && $y > $previousX - $previous) {
                $edits[] = [OperationKind::Equal, $old[--$x]];
                --$y;
            }

            $edits[] = $x === $previousX ? [OperationKind::Insert, $new[--$y]] : [OperationKind::Delete, $old[--$x]];
        }

        return array_reverse($edits);
    }

    /**
     * @param list<string> $tokens
     *
     * @return list<array{OperationKind, string}>
     */
    private static function same(array $tokens): array
    {
        return self::all(OperationKind::Equal, $tokens);
    }

    /**
     * @param list<string> $tokens
     *
     * @return list<array{OperationKind, string}>
     */
    private static function all(OperationKind $kind, array $tokens): array
    {
        return array_map(static fn (string $token): array => [$kind, $token], $tokens);
    }

    /**
     * Kept white space between two changes, made part of them: deleted and inserted again.
     *
     * @param list<Operation> $operations
     *
     * @return list<array{OperationKind, string}>
     */
    private static function spaces(array $operations): array
    {
        $edits = [];
        $last = \count($operations) - 1;

        foreach ($operations as $index => $operation) {
            if (OperationKind::Equal === $operation->kind && $index > 0 && $index < $last && '' === trim($operation->text())) {
                array_push($edits, ...self::all(OperationKind::Delete, $operation->tokens), ...self::all(OperationKind::Insert, $operation->tokens));
            } else {
                array_push($edits, ...self::all($operation->kind, $operation->tokens));
            }
        }

        return $edits;
    }

    /**
     * Single edits gathered into operations: kept runs and changes alternate, and each change has its deletions before
     * its insertions.
     *
     * @param list<array{OperationKind, string}> $edits
     *
     * @return list<Operation>
     */
    private static function group(array $edits): array
    {
        $operations = [];
        $runs = [OperationKind::Equal->value => [], OperationKind::Delete->value => [], OperationKind::Insert->value => []];

        foreach ($edits as [$kind, $token]) {
            if (OperationKind::Equal === $kind) {
                self::flush($operations, $runs, OperationKind::Delete);
                self::flush($operations, $runs, OperationKind::Insert);
            } else {
                self::flush($operations, $runs, OperationKind::Equal);
            }

            $runs[$kind->value][] = $token;
        }

        foreach (OperationKind::cases() as $kind) {
            self::flush($operations, $runs, $kind);
        }

        return $operations;
    }

    /**
     * @param list<Operation>             $operations
     * @param array<string, list<string>> $runs
     */
    private static function flush(array &$operations, array &$runs, OperationKind $kind): void
    {
        if ([] !== $runs[$kind->value]) {
            $operations[] = new Operation($kind, $runs[$kind->value]);
            $runs[$kind->value] = [];
        }
    }
}
