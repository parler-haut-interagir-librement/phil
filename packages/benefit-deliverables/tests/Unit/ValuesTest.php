<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables\Tests\Unit;

use DateTimeImmutable;
use Phil\Address\Address;
use Phil\BenefitDeliverables\Benefit;
use Phil\BenefitDeliverables\ChecklistItem;
use Phil\BenefitDeliverables\DeliverableKey;
use Phil\BenefitDeliverables\Delivery;
use Phil\BenefitDeliverables\Entitlement;
use Phil\BenefitDeliverables\Exception\InvalidDeliverable;
use Phil\BenefitDeliverables\Member;
use Phil\BenefitDeliverables\Period;
use Phil\BenefitDeliverables\Shipping\ShipmentReceipt;
use Phil\BenefitDeliverables\Shipping\ShipmentRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Period::class)]
#[CoversClass(Benefit::class)]
#[CoversClass(Member::class)]
#[CoversClass(Entitlement::class)]
#[CoversClass(DeliverableKey::class)]
#[CoversClass(Delivery::class)]
#[CoversClass(ChecklistItem::class)]
#[CoversClass(ShipmentRequest::class)]
#[CoversClass(ShipmentReceipt::class)]
final class ValuesTest extends TestCase
{
    public function testAPeriodIsNamed(): void
    {
        self::assertSame('2026-Q4', Period::of('2026-Q4')->name);
        self::assertSame('a', Period::of('a')->name);
        self::assertSame('9'.str_repeat('x', 31), Period::of('9'.str_repeat('x', 31))->name);
        self::assertSame('2026.W47_a:b', Period::of('2026.W47_a:b')->name);
        self::assertTrue(Period::of('2026-11')->equals(Period::of('2026-11')));
        self::assertFalse(Period::of('2026-11')->equals(Period::of('2026-12')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function periods(): iterable
    {
        yield 'empty' => [''];
        yield 'leading dash' => ['-2026'];
        yield 'space' => ['2026 Q4'];
        yield 'too long' => [str_repeat('x', 33)];
        yield 'trailing new line' => ["2026\n"];
        yield 'slash' => ['2026/11'];
    }

    #[DataProvider('periods')]
    public function testAWrongPeriodIsRefused(string $name): void
    {
        $this->expectException(InvalidDeliverable::class);
        $this->expectExceptionMessageIsOrContains('A period is named by 1 to 32 letters');

        Period::of($name);
    }

    public function testABenefitIsCodedAndLabelled(): void
    {
        $postcard = Benefit::of('postcard', '  A postcard  ', true);

        self::assertSame('postcard', $postcard->code);
        self::assertSame('A postcard', $postcard->label);
        self::assertTrue($postcard->ships);
        self::assertFalse(Benefit::of('credits', 'Credits')->ships);
        self::assertSame(str_repeat('a', 64), Benefit::of(str_repeat('a', 64), 'x')->code);
        self::assertSame('0_.:-', Benefit::of('0_.:-', 'x')->code);
        self::assertSame(str_repeat('é', 255), Benefit::of('x', str_repeat('é', 255))->label);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function benefits(): iterable
    {
        yield 'empty code' => ['', 'x', 'A benefit is coded'];
        yield 'upper case' => ['Postcard', 'x', 'A benefit is coded'];
        yield 'leading dash' => ['-x', 'x', 'A benefit is coded'];
        yield 'too long code' => [str_repeat('a', 65), 'x', 'A benefit is coded'];
        yield 'trailing new line' => ["postcard\n", 'x', 'A benefit is coded'];
        yield 'empty label' => ['postcard', '  ', 'The label of the benefit "postcard" is 1 to 255 characters.'];
        yield 'long label' => ['postcard', str_repeat('é', 256), 'The label of the benefit "postcard" is 1 to 255 characters.'];
    }

    #[DataProvider('benefits')]
    public function testAWrongBenefitIsRefused(string $code, string $label, string $message): void
    {
        $this->expectException(InvalidDeliverable::class);
        $this->expectExceptionMessageIsOrContains($message);

        Benefit::of($code, $label);
    }

    public function testAMemberIsAStableIdentifier(): void
    {
        self::assertSame('m-1', Member::check('m-1'));
        self::assertSame(str_repeat('m', 255), Member::check(str_repeat('m', 255)));

        foreach (['' => 'A member is named by 1 to 255 characters.', '   ' => 'A member is named by 1 to 255 characters.', str_repeat('m', 256) => 'A member is named by 1 to 255 characters.', 'ada@example.org' => 'never an email address'] as $member => $message) {
            try {
                Member::check($member);
                self::fail(\sprintf('"%s" was taken for a member.', $member));
            } catch (InvalidDeliverable $invalidDeliverable) {
                self::assertStringContainsString($message, $invalidDeliverable->getMessage());
            }
        }
    }

    public function testAnEntitlementIndexesItsBenefits(): void
    {
        $postcard = Benefit::of('postcard', 'Postcard', true);
        $credits = Benefit::of('credits', 'Credits');
        $entitlement = new Entitlement('m-1', [$postcard, $credits]);

        self::assertSame('m-1', $entitlement->member);
        self::assertSame(['postcard' => $postcard, 'credits' => $credits], $entitlement->benefits);
        self::assertSame($credits, $entitlement->benefit('credits'));
        self::assertNull($entitlement->benefit('game'));
        self::assertCount(100, new Entitlement('m', array_map(static fn (int $i): Benefit => Benefit::of('b'.$i, 'B'), range(1, 100)))->benefits);
    }

    public function testAnEntitlementIsBounded(): void
    {
        $this->expectException(InvalidDeliverable::class);
        $this->expectExceptionMessageIsOrContains('A member is owed 100 benefits a period at most.');

        new Entitlement('m', array_map(static fn (int $i): Benefit => Benefit::of('b'.$i, 'B'), range(1, 101)));
    }

    public function testAnEntitlementChecksItsMember(): void
    {
        $this->expectException(InvalidDeliverable::class);

        new Entitlement('ada@example.org', []);
    }

    public function testKeysAndItems(): void
    {
        $key = new DeliverableKey(Period::of('2026-11'), 'postcard', 'm-1');

        self::assertTrue($key->equals(new DeliverableKey(Period::of('2026-11'), 'postcard', 'm-1')));
        self::assertFalse($key->equals(new DeliverableKey(Period::of('2026-12'), 'postcard', 'm-1')));
        self::assertFalse($key->equals(new DeliverableKey(Period::of('2026-11'), 'credits', 'm-1')));
        self::assertFalse($key->equals(new DeliverableKey(Period::of('2026-11'), 'postcard', 'm-2')));

        $benefit = Benefit::of('postcard', 'Postcard', true);
        self::assertFalse(new ChecklistItem($key, $benefit, null)->isDone());
        $delivery = new Delivery($key, new DateTimeImmutable('2026-11-20'), 'staff-1');
        self::assertTrue(new ChecklistItem($key, $benefit, $delivery)->isDone());
        self::assertSame('staff-1', $delivery->by);
    }

    public function testAShipmentRequestAndItsReceipt(): void
    {
        $request = new ShipmentRequest('S-1', Address::of('CA', ['locality' => 'Montréal']), 'postcard', '+15145550123');

        self::assertSame('S-1', $request->reference);
        self::assertSame('+15145550123', $request->phone);
        self::assertNull(new ShipmentRequest(str_repeat('r', 64), Address::of('CA'), 'postcard')->phone);
        self::assertSame('1Z', new ShipmentReceipt(str_repeat('c', 64), '1Z')->trackingNumber);
        $refused = [];

        foreach ([
            static fn (): ShipmentRequest => new ShipmentRequest(' ', Address::of('CA'), 'postcard'),
            static fn (): ShipmentRequest => new ShipmentRequest(str_repeat('r', 65), Address::of('CA'), 'postcard'),
            static fn (): ShipmentRequest => new ShipmentRequest('S-1', Address::of('CA'), 'postcard', '5145550123'),
            static fn (): ShipmentRequest => new ShipmentRequest('S-1', Address::of('CA'), 'postcard', "+15145550123\n"),
            static fn (): ShipmentReceipt => new ShipmentReceipt('', '1Z'),
            static fn (): ShipmentReceipt => new ShipmentReceipt(str_repeat('c', 65), '1Z'),
            static fn (): ShipmentReceipt => new ShipmentReceipt('post', ' '),
            static fn (): ShipmentReceipt => new ShipmentReceipt('post', str_repeat('1', 65)),
        ] as $index => $build) {
            try {
                $build();
                self::fail(\sprintf('Case %d was accepted.', $index));
            } catch (InvalidDeliverable) {
                $refused[] = $index;
            }
        }

        self::assertSame(range(0, 7), $refused);
    }
}
