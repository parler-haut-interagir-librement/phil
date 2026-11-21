<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Tests\Unit\Rule;

use DateTimeImmutable;
use Phil\ImportWizard\Exception\InvalidProfile;
use Phil\ImportWizard\Plan\CellError;
use Phil\ImportWizard\Plan\Reason;
use Phil\ImportWizard\Rule\DateOrder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DateOrder::class)]
final class DateOrderTest extends TestCase
{
    public function testAnEndBeforeItsStartIsRefused(): void
    {
        $rule = new DateOrder('starts_on', 'ends_on');
        $start = new DateTimeImmutable('2026-11-19');

        self::assertEquals([new CellError('ends_on', Reason::DateOrder, 'starts_on')], $rule->check(['starts_on' => $start, 'ends_on' => new DateTimeImmutable('2026-11-18')]));
        self::assertSame([], $rule->check(['starts_on' => $start, 'ends_on' => new DateTimeImmutable('2026-11-19')]));
        self::assertSame([], $rule->check(['starts_on' => $start, 'ends_on' => new DateTimeImmutable('2026-11-20')]));
        self::assertSame([], $rule->check(['starts_on' => $start, 'ends_on' => null]));
        self::assertSame([], $rule->check(['ends_on' => $start]));
        self::assertSame([], $rule->check(['starts_on' => 'x', 'ends_on' => $start]));
        self::assertSame([], $rule->check(['starts_on' => $start, 'ends_on' => 'x']));
    }

    public function testItSerialisesAndReadsBack(): void
    {
        $rule = new DateOrder('a', 'b');

        self::assertSame('date-order', DateOrder::name());
        self::assertSame(['start' => 'a', 'end' => 'b'], $rule->toArray());
        self::assertEquals($rule, DateOrder::fromArray(['start' => 'a', 'end' => 'b']));

        $this->expectException(InvalidProfile::class);
        $this->expectExceptionMessageIsOrContains('The field "rule.end" must be a text.');

        DateOrder::fromArray(['start' => 'a']);
    }

    public function testItNamesTheStartWhenItIsMissing(): void
    {
        $this->expectExceptionMessageIsOrContains('The field "rule.start" must be a text.');

        DateOrder::fromArray(['end' => 'a']);
    }
}
