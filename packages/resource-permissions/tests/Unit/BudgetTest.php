<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Tests\Unit;

use Phil\ResourcePermissions\Budget;
use Phil\ResourcePermissions\Exception\InvalidRelation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Budget::class)]
final class BudgetTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $budget = new Budget();

        self::assertSame([32, 500, 200, 1000], [$budget->maxDepth, $budget->maxReads, $budget->maxFanOut, $budget->maxCandidates]);
    }

    public function testTheBoundsAreAccepted(): void
    {
        $low = new Budget(1, 1, 1, 1);
        $high = new Budget(Budget::MAX, Budget::MAX, Budget::MAX, Budget::MAX);

        self::assertSame([1, 1, 1, 1], [$low->maxDepth, $low->maxReads, $low->maxFanOut, $low->maxCandidates]);
        self::assertSame([100_000, 100_000, 100_000, 100_000], [$high->maxDepth, $high->maxReads, $high->maxFanOut, $high->maxCandidates]);
    }

    /**
     * @param array{int, int, int, int} $limits
     */
    #[DataProvider('outOfBounds')]
    public function testALimitOutOfBoundsIsRefused(array $limits, string $message): void
    {
        $this->expectException(InvalidRelation::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Budget(...$limits);
    }

    /**
     * @return iterable<array{array{int, int, int, int}, string}>
     */
    public static function outOfBounds(): iterable
    {
        yield [[0, 1, 1, 1], "budget's maxDepth is between 1 and 100000; 0 is not."];
        yield [[1, 0, 1, 1], 'maxReads'];
        yield [[1, 1, 0, 1], 'maxFanOut'];
        yield [[1, 1, 1, 0], 'maxCandidates'];
        yield [[100_001, 1, 1, 1], 'maxDepth'];
        yield [[1, 100_001, 1, 1], 'maxReads'];
        yield [[1, 1, 100_001, 1], 'maxFanOut'];
        yield [[1, 1, 1, 100_001], 'maxCandidates'];
    }
}
