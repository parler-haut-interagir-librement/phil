<?php

declare(strict_types=1);

namespace Phil\Rules\Tests\Unit\Fact;

use Phil\Rules\Exception\MissingFact;
use Phil\Rules\Fact\Facts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Facts::class)]
#[CoversClass(MissingFact::class)]
final class FactsTest extends TestCase
{
    private int $calls = 0;

    public function testGivesValues(): void
    {
        $facts = Facts::of(['country' => 'CA', 'none' => null]);

        self::assertTrue($facts->has('country'));
        self::assertTrue($facts->has('none'));
        self::assertFalse($facts->has('city'));
        self::assertSame('CA', $facts->get('country'));
        self::assertNull($facts->get('none'));
    }

    public function testComputesAClosureOnceWhenFirstRead(): void
    {
        $facts = Facts::of(['orders' => function (): ?int {
            ++$this->calls;

            return 1 === $this->calls ? null : 99;
        }]);

        self::assertSame(0, $this->calls);
        self::assertNull($facts->get('orders'));
        self::assertNull($facts->get('orders'));
        self::assertSame(1, $this->calls);
    }

    public function testRefusesAMissingFact(): void
    {
        $this->expectException(MissingFact::class);
        $this->expectExceptionMessageIsOrContains('The scope holds no fact "ci?y".');

        Facts::of([])->get("ci\x01y");
    }
}
