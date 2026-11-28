<?php

declare(strict_types=1);

namespace Phil\Rules\Tests\Unit;

use Phil\Rules\Exception\InvalidLimit;
use Phil\Rules\Limits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Limits::class)]
#[CoversClass(InvalidLimit::class)]
final class LimitsTest extends TestCase
{
    public function testHasDefaults(): void
    {
        $limits = new Limits();

        self::assertSame([65536, 16, 256, 256], [$limits->maxBytes, $limits->maxDepth, $limits->maxNodes, $limits->maxEvaluations]);
    }

    public function testAcceptsItsBounds(): void
    {
        $low = new Limits(1, 1, 1, 1);
        $high = new Limits(1048576, 64, 10000, 100000);

        self::assertSame([1, 1, 1, 1], [$low->maxBytes, $low->maxDepth, $low->maxNodes, $low->maxEvaluations]);
        self::assertSame([1048576, 64, 10000, 100000], [$high->maxBytes, $high->maxDepth, $high->maxNodes, $high->maxEvaluations]);
    }

    /**
     * @return iterable<string, array{array<string, int>, string}>
     */
    public static function outOfRange(): iterable
    {
        yield 'no bytes' => [['maxBytes' => 0], 'maxBytes is 0; it must lie between 1 and 1048576.'];
        yield 'too many bytes' => [['maxBytes' => 1048577], 'maxBytes is 1048577; it must lie between 1 and 1048576.'];
        yield 'no depth' => [['maxDepth' => 0], 'maxDepth is 0; it must lie between 1 and 64.'];
        yield 'too deep' => [['maxDepth' => 65], 'maxDepth is 65; it must lie between 1 and 64.'];
        yield 'no nodes' => [['maxNodes' => 0], 'maxNodes is 0; it must lie between 1 and 10000.'];
        yield 'too many nodes' => [['maxNodes' => 10001], 'maxNodes is 10001; it must lie between 1 and 10000.'];
        yield 'no evaluations' => [['maxEvaluations' => 0], 'maxEvaluations is 0; it must lie between 1 and 100000.'];
        yield 'too many evaluations' => [['maxEvaluations' => 100001], 'maxEvaluations is 100001; it must lie between 1 and 100000.'];
    }

    /**
     * @param array<string, int> $limits
     */
    #[DataProvider('outOfRange')]
    public function testRefusesABoundOutOfRange(array $limits, string $message): void
    {
        $this->expectException(InvalidLimit::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Limits(...$limits);
    }
}
