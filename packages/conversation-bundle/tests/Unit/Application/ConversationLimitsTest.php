<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Tests\Unit\Application;

use InvalidArgumentException;
use Phil\ConversationBundle\Application\ConversationLimits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConversationLimits::class)]
final class ConversationLimitsTest extends TestCase
{
    public function testTheDefaultsAndThePages(): void
    {
        $limits = new ConversationLimits();

        self::assertSame([10_000, 200, 50, 50], [$limits->bodyLength, $limits->subjectLength, $limits->groupSize, $limits->pageSize]);
        self::assertSame(50, $limits->page(null));
        self::assertSame(20, $limits->page(20));
        self::assertSame(50, $limits->page(51));
        self::assertSame(1, $limits->page(0));
        self::assertSame(1, $limits->page(-4));
        self::assertSame(1, $limits->page(1));
        self::assertSame(50, $limits->page(50));

        $edges = new ConversationLimits(1_000_000, 1000, 1000, 200);
        self::assertSame([1_000_000, 1000, 1000, 200], [$edges->bodyLength, $edges->subjectLength, $edges->groupSize, $edges->pageSize]);
    }

    /**
     * @param array{positive-int, positive-int, int<2, max>, positive-int} $limits
     */
    #[DataProvider('tooLarge')]
    public function testLimitsBeyondTheBoundsAreRefused(array $limits): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The limits of the conversations stay within 1 000 000 characters a message, 1 000 a subject, 1 000 participants a group and 200 lines a page.');

        new ConversationLimits(...$limits);
    }

    /**
     * @return iterable<string, array{array{positive-int, positive-int, int<2, max>, positive-int}}>
     */
    public static function tooLarge(): iterable
    {
        yield 'body' => [[1_000_001, 200, 50, 50]];
        yield 'subject' => [[10_000, 1001, 50, 50]];
        yield 'group' => [[10_000, 200, 1001, 50]];
        yield 'page' => [[10_000, 200, 50, 201]];
    }
}
