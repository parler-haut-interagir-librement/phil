<?php

declare(strict_types=1);

namespace Phil\Assignment\Internal;

use SplMinHeap;

/**
 * A minimum-cost maximum flow on a small integer network, by the primal-dual method: Dijkstra's algorithm on costs
 * reduced by node potentials prices the network, so that every residual cost stays non-negative and the shortest
 * paths cost zero; depth-first searches then push flow along those paths until none is left, and the network is
 * priced again. At most one pricing per augmentation, O(E log V) each, and far fewer when costs are small integers.
 *
 * The queue holds each entry as one integer, distance times the number of nodes plus the node, so that ties go to the
 * lowest node number and the same network always gives the same flow.
 *
 * @internal
 */
final class MinCostFlow
{
    /** @var list<int> */
    private array $to = [];

    /** @var array<int, int> */
    private array $capacity = [];

    /** @var list<int> */
    private array $cost = [];

    /** @var array<int, list<int>> */
    private array $edges = [];

    public function __construct(
        private readonly int $nodes,
    ) {
        $this->edges = array_fill(0, $nodes, []);
    }

    /**
     * Adds an edge and its residual twin; gives back the edge's number.
     */
    public function addEdge(int $from, int $to, int $capacity, int $cost): int
    {
        $edge = \count($this->to);
        $this->to[] = $to;
        $this->capacity[] = $capacity;
        $this->cost[] = $cost;
        $this->edges[$from][] = $edge;
        $this->to[] = $from;
        $this->capacity[] = 0;
        $this->cost[] = -$cost;
        $this->edges[$to][] = $edge + 1;

        return $edge;
    }

    /**
     * Whether an edge carries flow.
     */
    public function isUsed(int $edge): bool
    {
        return $this->capacity[$edge + 1] > 0;
    }

    /**
     * Pushes one unit along a path of edges, before solving: a warm start that must leave the flow of the lowest cost
     * for its value, and the potentials given to `solve()` valid for it.
     *
     * @param list<int> $path
     */
    public function push(array $path): void
    {
        foreach ($path as $edge) {
            --$this->capacity[$edge];
            ++$this->capacity[$edge ^ 1];
        }
    }

    /**
     * Pushes as much flow as it can from the source to the sink, at the lowest cost.
     *
     * @param array<int, int> $potential the starting potential of each node, which keeps every residual cost
     *                                   non-negative; all zero for a network without flow
     */
    public function solve(int $source, int $sink, Meter $meter, array $potential = []): void
    {
        $potential += array_fill(0, $this->nodes, 0);

        while ($this->reprice($source, $sink, $meter, $potential)) {
            $this->saturate($source, $sink, $meter, $potential);
        }
    }

    /**
     * Dijkstra's algorithm on the reduced costs, stopped at the sink; then every potential grows by its node's distance,
     * or by the sink's for a node further away, so that the shortest paths to the sink cost zero and no residual edge
     * costs less than zero. False when the sink cannot be reached.
     *
     * @param array<int, int> $potential
     */
    private function reprice(int $source, int $sink, Meter $meter, array &$potential): bool
    {
        $width = $this->nodes;
        $distance = array_fill(0, $width, \PHP_INT_MAX);
        $done = [];
        $distance[$source] = 0;
        $queue = new SplMinHeap();
        $queue->insert($source);

        while (!$queue->isEmpty()) {
            /** @var int $top */
            $top = $queue->extract();
            $node = $top % $width;

            if (isset($done[$node])) {
                continue;
            }

            $done[$node] = true;

            if ($node === $sink) {
                break;
            }

            $meter->spend(\count($this->edges[$node]));

            foreach ($this->edges[$node] as $edge) {
                $next = $this->to[$edge];

                if (0 === $this->capacity[$edge]) {
                    continue;
                }

                $candidate = $distance[$node] + $this->cost[$edge] + $potential[$node] - $potential[$next];

                if ($candidate < $distance[$next]) {
                    $distance[$next] = $candidate;
                    $queue->insert($candidate * $width + $next);
                }
            }
        }

        if (\PHP_INT_MAX === $distance[$sink]) {
            return false;
        }

        foreach ($distance as $node => $length) {
            $potential[$node] += min($length, $distance[$sink]);
        }

        return true;
    }

    /**
     * Pushes flow along paths of zero reduced cost, depth first, until none is left: all of them are shortest paths, so
     * that one pricing serves many augmentations. A node from which the sink cannot be reached is not visited again in
     * the same pass, and each node resumes at the edge it stopped at.
     *
     * @param array<int, int> $potential
     */
    private function saturate(int $source, int $sink, Meter $meter, array $potential): void
    {
        $resume = array_fill(0, $this->nodes, 0);
        $dead = [];

        while (true) {
            $path = [];
            $stack = [$source];
            $onPath = [$source => true];
            $node = $source;

            while ($node !== $sink) {
                $meter->spend();
                $advanced = false;
                $edges = $this->edges[$node];

                for ($count = \count($edges); $resume[$node] < $count; ++$resume[$node]) {
                    $edge = $edges[$resume[$node]];
                    $next = $this->to[$edge];

                    if ($this->capacity[$edge] > 0 && !isset($dead[$next]) && !isset($onPath[$next])
                        && 0 === $this->cost[$edge] + $potential[$node] - $potential[$next]) {
                        $path[] = $edge;
                        $stack[] = $next;
                        $onPath[$next] = true;
                        $node = $next;
                        $advanced = true;

                        break;
                    }
                }

                if ($advanced) {
                    continue;
                }

                if ($node === $source) {
                    return;
                }

                $dead[$node] = true;
                unset($onPath[$node]);
                array_pop($stack);
                array_pop($path);
                $node = $stack[\count($stack) - 1];
                ++$resume[$node];
            }

            $bottleneck = \PHP_INT_MAX;

            foreach ($path as $edge) {
                $bottleneck = min($bottleneck, $this->capacity[$edge]);
            }

            foreach ($path as $edge) {
                $this->capacity[$edge] -= $bottleneck;
                $this->capacity[$edge ^ 1] += $bottleneck;
            }
        }
    }
}
