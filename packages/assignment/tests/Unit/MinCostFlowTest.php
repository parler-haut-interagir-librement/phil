<?php

declare(strict_types=1);

namespace Phil\Assignment\Tests\Unit;

use Phil\Assignment\Exception\BudgetExceeded;
use Phil\Assignment\Internal\Meter;
use Phil\Assignment\Internal\MinCostFlow;
use Phil\Assignment\WorkBudget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use ReflectionProperty;

#[CoversClass(MinCostFlow::class)]
#[CoversClass(Meter::class)]
final class MinCostFlowTest extends TestCase
{
    public function testAPushedPathCarriesFlowBeforeSolving(): void
    {
        $flow = new MinCostFlow(3);
        $first = $flow->addEdge(0, 1, 1, 0);
        $second = $flow->addEdge(1, 2, 1, 0);
        $spare = $flow->addEdge(0, 2, 1, 5);

        self::assertFalse($flow->isUsed($first));

        $flow->push([$first, $second]);

        self::assertTrue($flow->isUsed($first));
        self::assertTrue($flow->isUsed($second));
        self::assertFalse($flow->isUsed($spare));
        self::assertSame([0, 1, 0, 1, 1, 0], $this->capacities($flow));
    }

    public function testEveryNodeOfTheNetworkTakesEdges(): void
    {
        $flow = new MinCostFlow(4);
        $edge = $flow->addEdge(3, 0, 1, 0);

        self::assertSame(0, $edge);
        self::assertSame([1, 0], $this->capacities($flow));
    }

    public function testASinkBeforeTheLastNodeIsReached(): void
    {
        $flow = new MinCostFlow(4);
        $first = $flow->addEdge(0, 1, 2, 1);
        $second = $flow->addEdge(1, 2, 1, 1);
        $third = $flow->addEdge(1, 3, 1, 1);
        $flow->solve(0, 1, new Meter(new WorkBudget()));

        self::assertTrue($flow->isUsed($first));
        self::assertFalse($flow->isUsed($second));
        self::assertFalse($flow->isUsed($third));
        self::assertSame(2, $this->flowOf($flow, $first));
    }

    public function testTheCostsAndFlowsMatchAReferenceOnRandomNetworks(): void
    {
        $random = new Randomizer(new Mt19937(35));

        for ($case = 0; $case < 300; ++$case) {
            $nodes = $random->getInt(2, 8);
            $edges = [];

            for ($count = $random->getInt(1, 16); $count > 0; --$count) {
                $from = $random->getInt(0, $nodes - 1);
                $to = $random->getInt(0, $nodes - 1);

                if ($from !== $to) {
                    $edges[] = [$from, $to, $random->getInt(1, 3), $random->getInt(0, 4)];
                }
            }

            $source = 0;
            $sink = 0 === $case % 2 ? $nodes - 1 : $random->getInt(1, $nodes - 1);
            $flow = new MinCostFlow($nodes);
            $numbers = [];

            foreach ($edges as [$from, $to, $capacity, $cost]) {
                $numbers[] = $flow->addEdge($from, $to, $capacity, $cost);
            }

            $flow->solve($source, $sink, new Meter(new WorkBudget()));

            $total = 0;
            $balance = array_fill(0, $nodes, 0);

            foreach ($edges as $position => [$from, $to, $capacity, $cost]) {
                $carried = $this->flowOf($flow, $numbers[$position]);
                self::assertGreaterThanOrEqual(0, $carried);
                self::assertLessThanOrEqual($capacity, $carried);
                self::assertSame($carried > 0, $flow->isUsed($numbers[$position]));
                $total += $carried * $cost;
                $balance[$from] -= $carried;
                $balance[$to] += $carried;
            }

            $value = $balance[$sink];

            foreach ($balance as $node => $surplus) {
                if ($node !== $source && $node !== $sink) {
                    self::assertSame(0, $surplus, 'Case '.$case);
                }
            }

            self::assertSame($this->reference($nodes, $edges, $source, $sink), [$value, $total], 'Case '.$case);
        }
    }

    public function testTheMeterCountsTheEdgesRelaxedAndTheNodesVisited(): void
    {
        $spent = $this->smallestBudget();

        self::assertSame(61, $spent);
    }

    /**
     * @return list<int>
     */
    private function capacities(MinCostFlow $flow): array
    {
        /** @var list<int> $capacity */
        $capacity = new ReflectionProperty(MinCostFlow::class, 'capacity')->getValue($flow);

        return $capacity;
    }

    private function flowOf(MinCostFlow $flow, int $edge): int
    {
        return $this->capacities($flow)[$edge + 1];
    }

    private function smallestBudget(): int
    {
        for ($budget = 1; $budget < 1_000; ++$budget) {
            $flow = new MinCostFlow(6);
            $flow->addEdge(0, 1, 2, 1);
            $flow->addEdge(0, 2, 2, 2);
            $flow->addEdge(1, 3, 1, 1);
            $flow->addEdge(1, 4, 2, 3);
            $flow->addEdge(2, 3, 1, 1);
            $flow->addEdge(2, 4, 1, 1);
            $flow->addEdge(3, 5, 2, 0);
            $flow->addEdge(4, 5, 3, 0);

            try {
                $flow->solve(0, 5, new Meter(new WorkBudget($budget)));
            } catch (BudgetExceeded) {
                continue;
            }

            return $budget;
        }

        self::fail('The flow never fits.');
    }

    /**
     * The value and the lowest cost of a maximum flow, by successive shortest paths with Bellman–Ford.
     *
     * @param list<array{int, int, int, int}> $edges
     *
     * @return array{int, int}
     */
    private function reference(int $nodes, array $edges, int $source, int $sink): array
    {
        $from = [];
        $to = [];
        $capacity = [];
        $cost = [];

        foreach ($edges as [$tail, $head, $room, $price]) {
            $from[] = $tail;
            $from[] = $head;
            $to[] = $head;
            $to[] = $tail;
            $capacity[] = $room;
            $capacity[] = 0;
            $cost[] = $price;
            $cost[] = -$price;
        }

        $value = 0;
        $total = 0;

        while (true) {
            $distance = array_fill(0, $nodes, \PHP_INT_MAX);
            $via = array_fill(0, $nodes, -1);
            $distance[$source] = 0;

            for ($round = 0; $round < $nodes; ++$round) {
                foreach ($from as $index => $tail) {
                    if ($capacity[$index] > 0 && \PHP_INT_MAX !== $distance[$tail] && $distance[$tail] + $cost[$index] < $distance[$to[$index]]) {
                        $distance[$to[$index]] = $distance[$tail] + $cost[$index];
                        $via[$to[$index]] = $index;
                    }
                }
            }

            if (\PHP_INT_MAX === $distance[$sink]) {
                return [$value, $total];
            }

            $bottleneck = \PHP_INT_MAX;

            for ($node = $sink; $node !== $source; $node = $from[$via[$node]]) {
                $bottleneck = min($bottleneck, $capacity[$via[$node]]);
            }

            for ($node = $sink; $node !== $source; $node = $from[$via[$node]]) {
                $capacity[$via[$node]] -= $bottleneck;
                $capacity[$via[$node] ^ 1] += $bottleneck;
            }

            $value += $bottleneck;
            $total += $bottleneck * $distance[$sink];
        }
    }
}
