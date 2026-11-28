<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables\Tests\Unit;

use DateTimeImmutable;
use Phil\BenefitDeliverables\Benefit;
use Phil\BenefitDeliverables\Checklist;
use Phil\BenefitDeliverables\ChecklistItem;
use Phil\BenefitDeliverables\ChecklistPage;
use Phil\BenefitDeliverables\DeliverableKey;
use Phil\BenefitDeliverables\Delivery;
use Phil\BenefitDeliverables\Entitlement;
use Phil\BenefitDeliverables\Exception\InvalidDeliverable;
use Phil\BenefitDeliverables\Period;
use Phil\BenefitDeliverables\Tests\Support\ForwardingDeliverables;
use Phil\BenefitDeliverables\Tests\Support\ForwardingEntitlements;
use Phil\BenefitDeliverables\Tests\Support\InMemoryDeliverables;
use Phil\BenefitDeliverables\Tests\Support\InMemoryEntitlements;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Checklist::class)]
#[CoversClass(ChecklistPage::class)]
final class ChecklistTest extends TestCase
{
    private Period $period;

    private InMemoryEntitlements $entitlements;

    private InMemoryDeliverables $deliveries;

    private Checklist $checklist;

    protected function setUp(): void
    {
        $this->period = Period::of('2026-11');
        $postcard = Benefit::of('postcard', 'Postcard', true);
        $credits = Benefit::of('credits', 'Credits');
        $this->entitlements = new InMemoryEntitlements()->owe(
            $this->period,
            new Entitlement('m-1', [$postcard, $credits]),
            new Entitlement('m-2', [$credits]),
            new Entitlement('m-3', [$postcard]),
        );
        $this->deliveries = new InMemoryDeliverables();
        $this->checklist = new Checklist($this->entitlements, $this->deliveries);
    }

    public function testEachBenefitOwedIsALine(): void
    {
        $page = $this->checklist->page($this->period);

        self::assertSame([['m-1', 'postcard'], ['m-1', 'credits'], ['m-2', 'credits'], ['m-3', 'postcard']], $this->lines($page));
        self::assertNull($page->next);
        self::assertSame([[null, 100]], $this->entitlements->pages);
        self::assertSame([['m-1', 'm-2', 'm-3']], $this->deliveries->reads);
    }

    public function testOneBenefitIsListedAlone(): void
    {
        self::assertSame([['m-1', 'postcard'], ['m-3', 'postcard']], $this->lines($this->checklist->page($this->period, 'postcard')));
    }

    public function testPagesFollowEachOther(): void
    {
        $first = $this->checklist->page($this->period, null, null, 2);
        self::assertSame('m-2', $first->next);
        self::assertSame([['m-1', 'postcard'], ['m-1', 'credits'], ['m-2', 'credits']], $this->lines($first));

        $second = $this->checklist->page($this->period, null, $first->next, 2);
        self::assertNull($second->next);
        self::assertSame([['m-3', 'postcard']], $this->lines($second));
        self::assertSame([[null, 2], ['m-2', 2]], $this->entitlements->pages);
    }

    public function testAFullLastPageLeadsToAnEmptyOne(): void
    {
        $page = $this->checklist->page($this->period, null, null, 3);
        self::assertSame('m-3', $page->next);

        $empty = $this->checklist->page($this->period, null, 'm-3', 3);
        self::assertSame([], $empty->items);
        self::assertNull($empty->next);
        self::assertSame([['m-1', 'm-2', 'm-3']], $this->deliveries->reads, 'an empty page reads no delivery');
    }

    public function testDoneLinesCarryTheirDeliveryAndLeaveTheRemaining(): void
    {
        $at = new DateTimeImmutable('2026-11-20');

        self::assertSame(2, $this->checklist->markDone($this->period, 'postcard', ['m-1', 'm-3', 'm-1'], $at, 'staff-1'));

        $page = $this->checklist->page($this->period, 'postcard');
        self::assertTrue($page->items[0]->isDone());
        self::assertSame('staff-1', $page->items[0]->delivery?->by);
        self::assertSame([['m-1', 'credits'], ['m-2', 'credits']], $this->lines($this->checklist->remaining($this->period)));
        self::assertSame([], $this->checklist->remaining($this->period, 'postcard')->items);
    }

    public function testMarkingAgainKeepsTheFirstDelivery(): void
    {
        $this->checklist->markDone($this->period, 'postcard', ['m-1'], new DateTimeImmutable('2026-11-20'), 'staff-1');

        self::assertSame(0, $this->checklist->markDone($this->period, 'postcard', ['m-1'], new DateTimeImmutable('2026-11-21'), 'staff-2'));
        self::assertSame('staff-1', $this->checklist->page($this->period, 'postcard')->items[0]->delivery?->by);
    }

    public function testAMemberNotOwedTheBenefitIsLeftOut(): void
    {
        self::assertSame(0, $this->checklist->markDone($this->period, 'postcard', ['m-2', 'm-9'], new DateTimeImmutable(), 'staff-1'));
        self::assertSame(0, $this->checklist->markDone(Period::of('2026-12'), 'postcard', ['m-1'], new DateTimeImmutable(), 'staff-1'));
        self::assertSame(0, $this->checklist->markDone($this->period, 'postcard', [], new DateTimeImmutable(), 'staff-1'));
        self::assertSame([], $this->deliveries->deliveries);
    }

    public function testAMarkIsUndone(): void
    {
        $this->checklist->markDone($this->period, 'postcard', ['m-1'], new DateTimeImmutable(), 'staff-1');

        self::assertTrue($this->checklist->unmark($this->period, 'postcard', 'm-1'));
        self::assertFalse($this->checklist->unmark($this->period, 'postcard', 'm-1'));
        self::assertFalse($this->checklist->page($this->period, 'postcard')->items[0]->isDone());
    }

    public function testADeliveryOfAnotherPeriodIsIgnored(): void
    {
        $this->deliveries->put(new Delivery(new DeliverableKey(Period::of('2026-10'), 'postcard', 'm-1'), new DateTimeImmutable(), 'staff-1'));
        $store = new class($this->deliveries) extends ForwardingDeliverables {};

        self::assertFalse(new Checklist($this->entitlements, $store)->page($this->period, 'postcard')->items[0]->isDone());
    }

    public function testPagesAndLotsAreBounded(): void
    {
        self::assertSame([], $this->checklist->page(Period::of('empty'), null, null, 1_000)->items);
        self::assertSame([], $this->checklist->page(Period::of('empty'), null, null, 1)->items);

        foreach ([0, 1_001] as $limit) {
            try {
                $this->checklist->remaining($this->period, null, null, $limit);
                self::fail(\sprintf('A page of %d was read.', $limit));
            } catch (InvalidDeliverable $invalidDeliverable) {
                self::assertSame('A page of the checklist reads 1 to 1000 members.', $invalidDeliverable->getMessage());
            }
        }

        $this->expectException(InvalidDeliverable::class);
        $this->expectExceptionMessageIsOrContains('A lot marks 1000 deliverables at most.');

        $this->checklist->markDone($this->period, 'postcard', array_map(static fn (int $i): string => 'm-'.$i, range(1, 1_001)), new DateTimeImmutable(), 'staff-1');
    }

    public function testALotOfAThousandIsMarked(): void
    {
        self::assertSame(2, $this->checklist->markDone($this->period, 'postcard', [...array_map(static fn (int $i): string => 'x-'.$i, range(1, 998)), 'm-1', 'm-3'], new DateTimeImmutable(), 'staff-1'));
    }

    public function testWhoMarksIsNamed(): void
    {
        foreach (['', '  ', str_repeat('s', 256)] as $by) {
            try {
                $this->checklist->markDone($this->period, 'postcard', ['m-1'], new DateTimeImmutable(), $by);
                self::fail('The mark was taken.');
            } catch (InvalidDeliverable $invalidDeliverable) {
                self::assertSame('Who marks a deliverable is named by 1 to 255 characters.', $invalidDeliverable->getMessage());
            }
        }

        self::assertSame(1, $this->checklist->markDone($this->period, 'postcard', ['m-1'], new DateTimeImmutable(), str_repeat('s', 255)));
    }

    public function testMembersAreChecked(): void
    {
        $this->expectException(InvalidDeliverable::class);
        $this->expectExceptionMessageIsOrContains('never an email address');

        $this->checklist->markDone($this->period, 'postcard', ['ada@example.org'], new DateTimeImmutable(), 'staff-1');
    }

    public function testASourceThatAnswersMoreThanAskedIsReadForTheMembersAskedOnly(): void
    {
        $source = new class($this->entitlements) extends ForwardingEntitlements {
            public function entitlementsOf(Period $period, array $members): array
            {
                return parent::entitlementsOf($period, ['m-1', 'm-3']);
            }
        };

        self::assertSame(1, new Checklist($source, $this->deliveries)->markDone($this->period, 'postcard', ['m-1'], new DateTimeImmutable(), 'staff-1'));
    }

    /**
     * @return list<array{string, string}>
     */
    private function lines(ChecklistPage $page): array
    {
        return array_map(static fn (ChecklistItem $item): array => [$item->key->member, $item->key->benefit], $page->items);
    }
}
