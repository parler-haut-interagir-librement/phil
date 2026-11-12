<?php

declare(strict_types=1);

namespace Phil\CreationDisclosure\Tests\Unit;

use Phil\CreationDisclosure\GenerativeAi;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(GenerativeAi::class)]
final class GenerativeAiTest extends TestCase
{
    /**
     * @return iterable<string, array{list<GenerativeAi>, GenerativeAi}>
     */
    public static function works(): iterable
    {
        yield 'no part' => [[], GenerativeAi::Undeclared];
        yield 'all AI-free' => [[GenerativeAi::None, GenerativeAi::None], GenerativeAi::None];
        yield 'all generated' => [[GenerativeAi::Full, GenerativeAi::Full], GenerativeAi::Full];
        yield 'one part generated' => [[GenerativeAi::None, GenerativeAi::Full], GenerativeAi::Partial];
        yield 'generated and undeclared' => [[GenerativeAi::Full, GenerativeAi::Undeclared], GenerativeAi::Partial];
        yield 'one part edited' => [[GenerativeAi::Partial], GenerativeAi::Partial];
        yield 'edited before possible' => [[GenerativeAi::Possible, GenerativeAi::Partial], GenerativeAi::Partial];
        yield 'possible before undeclared' => [[GenerativeAi::Undeclared, GenerativeAi::Possible], GenerativeAi::Possible];
        yield 'undeclared before AI-free' => [[GenerativeAi::None, GenerativeAi::Undeclared], GenerativeAi::Undeclared];
        yield 'possible alone' => [[GenerativeAi::None, GenerativeAi::Possible], GenerativeAi::Possible];
    }

    /**
     * @param list<GenerativeAi> $parts
     */
    #[DataProvider('works')]
    public function testAWorkCombinesItsParts(array $parts, GenerativeAi $whole): void
    {
        self::assertSame($whole, GenerativeAi::combine(...$parts));
    }

    public function testViewsAreOrdered(): void
    {
        $order = [GenerativeAi::None, GenerativeAi::Undeclared, GenerativeAi::Possible, GenerativeAi::Partial, GenerativeAi::Full];

        foreach ($order as $i => $view) {
            foreach ($order as $j => $limit) {
                self::assertSame($i <= $j, $view->isAtMost($limit), \sprintf('%s at most %s', $view->value, $limit->value));
            }
        }
    }
}
