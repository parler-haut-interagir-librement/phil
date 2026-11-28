<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Tests\Unit\Index;

use Phil\RelatedContent\Exception\InvalidRule;
use Phil\RelatedContent\Index\KeywordIndex;
use Phil\RelatedContent\Item;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(KeywordIndex::class)]
#[CoversClass(InvalidRule::class)]
final class KeywordIndexTest extends TestCase
{
    public function testEachSharedKeywordCountsTheWeight(): void
    {
        $index = new KeywordIndex('topics', 100.0);
        $a = new Item('a', ['topics' => ['epic', 'dark', 'dragons'], 'authors' => ['ada']]);
        $b = new Item('b', ['topics' => ['Dragons', 'Dark', 'heist'], 'authors' => ['ada']]);

        self::assertSame(200.0, $index->score($a, $b));
        self::assertSame(200.0, $index->score($b, $a));
        self::assertSame(0.0, $index->score($a, new Item('c', ['authors' => ['ada']])));
        self::assertSame('topics', $index->name);
        self::assertSame(100.0, $index->weight);
        self::assertSame(0.0, $index->broaderFactor);
    }

    public function testBroaderTermsCountAtTheirFactor(): void
    {
        $index = new KeywordIndex('topics', 100.0, 0.25);
        $a = new Item('a', ['topics' => ['epic', 'magic']], broader: ['topics' => ['fantasy', 'fiction']]);
        $b = new Item('b', ['topics' => ['dark', 'magic']], broader: ['topics' => ['fantasy', 'fiction']]);
        $c = new Item('c', ['topics' => ['fantasy']]);

        // magic is shared; fantasy and fiction are shared broader terms.
        self::assertSame(150.0, $index->score($a, $b));
        self::assertSame(150.0, $index->score($b, $a));
        self::assertSame(25.0, $index->score($a, $c));
        self::assertSame(100.0, new KeywordIndex('topics', 100.0)->score($a, $b));
    }

    public function testTheExtremeFactorsAreAccepted(): void
    {
        $a = new Item('a', ['topics' => ['x']], broader: ['topics' => ['y']]);
        $b = new Item('b', ['topics' => ['z']], broader: ['topics' => ['y']]);

        self::assertSame(10.0, new KeywordIndex('topics', 10.0, 1.0)->score($a, $b));
        self::assertSame(0.0, new KeywordIndex('topics', 10.0, 0.0)->score($a, $b));
        self::assertSame(0.0, new KeywordIndex('topics', 0.0, 1.0)->score($a, $b));
    }

    public function testAnIndexNeedsAName(): void
    {
        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains('An index needs a name.');

        new KeywordIndex('', 1.0);
    }

    /**
     * @return iterable<string, array{float, float, string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'negative weight' => [-0.1, 0.0, 'topics weight is -0.1; it must lie between 0 and '];
        yield 'infinite weight' => [\INF, 0.0, 'topics weight is INF'];
        yield 'not a number' => [\NAN, 0.0, 'topics weight is NAN'];
        yield 'negative factor' => [1.0, -0.01, 'topics broaderFactor is -0.01; it must lie between 0 and 1.'];
        yield 'factor above one' => [1.0, 1.01, 'topics broaderFactor is 1.01; it must lie between 0 and 1.'];
    }

    #[DataProvider('invalidValues')]
    public function testAValueOutOfBoundsIsRefused(float $weight, float $factor, string $message): void
    {
        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains($message);

        new KeywordIndex('topics', $weight, $factor);
    }
}
