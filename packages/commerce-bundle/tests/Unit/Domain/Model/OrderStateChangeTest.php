<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\Model;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Model\OrderStateChange;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrderStateChange::class)]
final class OrderStateChangeTest extends TestCase
{
    public function testATransitionIsJournalledWithoutAReason(): void
    {
        $at = new DateTimeImmutable('2026-11-11T10:00:00Z');
        $move = OrderStateChange::transition('2026-0001', OrderState::Cart, OrderState::Placed, 'place', 'clerk-1', $at);

        self::assertSame('2026-0001', $move->orderNumber);
        self::assertSame(OrderState::Cart, $move->from);
        self::assertSame(OrderState::Placed, $move->to);
        self::assertSame('place', $move->transition);
        self::assertSame('clerk-1', $move->actor);
        self::assertNull($move->reason);
        self::assertSame($at, $move->occurredAt);
        self::assertFalse($move->wasForced());
        self::assertNull(OrderStateChange::transition('2026-0001', OrderState::Cart, OrderState::Placed, 'place', null, $at)->actor);
    }

    public function testAForcedMoveIsJournalledWithItsReason(): void
    {
        $move = OrderStateChange::forced('2026-0001', OrderState::Cancelled, OrderState::Placed, 'manager-1', 'Cancelled by mistake', new DateTimeImmutable());

        self::assertNull($move->transition);
        self::assertSame('manager-1', $move->actor);
        self::assertSame('Cancelled by mistake', $move->reason);
        self::assertTrue($move->wasForced());
        self::assertNotSame($move->id, OrderStateChange::forced('2026-0001', OrderState::Cancelled, OrderState::Placed, 'manager-1', 'Again', new DateTimeImmutable())->id);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedReasons(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['  '];
        yield 'too long' => [str_repeat('r', 256)];
        yield 'two lines' => ["a\nb"];
    }

    #[DataProvider('refusedReasons')]
    public function testAForcedMoveHasAReason(string $reason): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A forced move has a reason: 1 to 255 characters on one line.');

        OrderStateChange::forced('2026-0001', OrderState::Cancelled, OrderState::Placed, 'manager-1', $reason, new DateTimeImmutable());
    }

    public function testTheLongestReasonIsAccepted(): void
    {
        self::assertSame(255, mb_strlen((string) OrderStateChange::forced('2026-0001', OrderState::Cancelled, OrderState::Placed, 'manager-1', str_repeat('é', 255), new DateTimeImmutable())->reason));
    }
}
