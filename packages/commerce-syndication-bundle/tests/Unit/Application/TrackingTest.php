<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceSyndicationBundle\Application\Inbox;
use Phil\CommerceSyndicationBundle\Application\Ledger;
use Phil\CommerceSyndicationBundle\Application\MessageData;
use Phil\CommerceSyndicationBundle\Application\PartnerReturns;
use Phil\CommerceSyndicationBundle\Application\ShipmentTracking;
use Phil\CommerceSyndicationBundle\Domain\Exception\SyndicationRefused;
use Phil\CommerceSyndicationBundle\Domain\Exception\UnknownPartner;
use Phil\CommerceSyndicationBundle\Domain\Model\LedgerEntry;
use Phil\CommerceSyndicationBundle\Domain\Model\OutgoingMessage;
use Phil\CommerceSyndicationBundle\Domain\Model\PartnerReturn;
use Phil\CommerceSyndicationBundle\Domain\Model\PartnerShipment;
use Phil\CommerceSyndicationBundle\Domain\Tracking\ReturnState;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\InMemorySyndication;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\Partners;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\RecordingTrackingHandler;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\StandardWebhooks\Ed25519SecretKey;
use Phil\StandardWebhooks\Signer;
use Phil\StandardWebhooks\WebhookHeaders;
use Phil\StandardWebhooks\WebhookId;
use Phil\StandardWebhooks\WebhookTimestamp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A supplier and a reseller, each with its own store, trading the parcels and the returns of a forwarded order through
 * signed messages.
 */
#[CoversClass(ShipmentTracking::class)]
#[CoversClass(PartnerReturns::class)]
#[CoversClass(MessageData::class)]
#[CoversClass(PartnerShipment::class)]
#[CoversClass(PartnerReturn::class)]
#[CoversClass(LedgerEntry::class)]
#[CoversClass(Inbox::class)]
final class TrackingTest extends TestCase
{
    private InMemorySyndication $supplierSide;

    private InMemorySyndication $resellerSide;

    private RecordingTrackingHandler $supplierHandler;

    private RecordingTrackingHandler $resellerHandler;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->at = new DateTimeImmutable('2026-11-12 10:00:00');
        $this->supplierSide = new InMemorySyndication();
        $this->resellerSide = new InMemorySyndication();
        $this->supplierHandler = new RecordingTrackingHandler();
        $this->resellerHandler = new RecordingTrackingHandler();
        $reseller = Partners::reseller('reseller', ['TEA-1', 'MUG']);
        $supplier = Partners::supplier();
        $this->supplierSide->addPartner($reseller);
        $this->resellerSide->addPartner($supplier);
        $lines = [['sku' => 'TEA-1', 'quantity' => 2, 'unit_cost' => 700], ['sku' => 'MUG', 'quantity' => 1, 'unit_cost' => 1_100]];
        $this->supplierSide->record(LedgerEntry::of($reseller, 'R-77', Money::of(2_500, Currency::of('CAD')), $this->at, $lines));
        $this->resellerSide->record(LedgerEntry::of($supplier, 'R-77', Money::of(-2_500, Currency::of('CAD')), $this->at, $lines));
    }

    public function testASupplierTellsTheResellerEachParcelOnce(): void
    {
        self::assertTrue($this->tracking($this->supplierSide)->notify('reseller', 'R-77', 'SH-1', ['TEA-1' => 2], $this->at, 'Canada Post', 'CP123'));
        self::assertFalse($this->tracking($this->supplierSide)->notify('reseller', 'R-77', 'SH-1', ['TEA-1' => 2], $this->at));

        $message = $this->supplierSide->outbox[0];
        self::assertSame(['shipment.sent', ['order' => 'R-77', 'shipment' => 'SH-1', 'carrier' => 'Canada Post', 'tracking' => 'CP123', 'lines' => [['sku' => 'TEA-1', 'quantity' => 2]]]], [$message->type, $message->data]);
        self::assertCount(1, $this->supplierSide->outbox);

        $this->deliver($message, Partners::supplierKey(), 'supplier', $this->resellerSide);
        $this->deliver($message, Partners::supplierKey(), 'supplier', $this->resellerSide, 'msg_again');

        $shipments = $this->tracking($this->resellerSide)->shipmentsOf('supplier', 'R-77');
        self::assertSame([['SH-1', ['TEA-1' => 2], 'Canada Post', 'CP123']], array_map(static fn (PartnerShipment $shipment): array => [$shipment->shipment, $shipment->lines, $shipment->carrier, $shipment->tracking], $shipments));
        self::assertSame($message->createdAt->getTimestamp(), $shipments[0]->shippedAt->getTimestamp());
        self::assertSame(['shipped SH-1'], $this->resellerHandler->calls);
    }

    public function testAParcelOfAnOrderNotForwardedOrBeyondItIsRefused(): void
    {
        try {
            $this->tracking($this->supplierSide)->notify('reseller', 'R-404', 'SH-1', ['TEA-1' => 1], $this->at);
            self::fail('A parcel of an unknown order was sent.');
        } catch (SyndicationRefused $syndicationRefused) {
            self::assertSame('No order R-404 is in the ledger with reseller.', $syndicationRefused->getMessage());
        }

        try {
            $this->tracking($this->supplierSide)->notify('reseller', 'R-77', 'SH-1', ['TEA-1' => 3], $this->at);
            self::fail('More units left than the order holds.');
        } catch (SyndicationRefused $syndicationRefused) {
            self::assertSame('The order R-77 holds no TEA-1.', $syndicationRefused->getMessage());
        }

        try {
            $this->tracking($this->supplierSide)->notify('nobody', 'R-77', 'SH-1', ['TEA-1' => 1], $this->at);
            self::fail('An unknown partner was told.');
        } catch (UnknownPartner) {
        }

        self::assertSame([], $this->supplierSide->outbox);
        self::assertSame([], $this->supplierSide->shipments);
    }

    public function testOnlyASupplierSendsParcels(): void
    {
        $this->resellerSide->addPartner(Partners::reseller('reseller', ['TEA-1']));

        $this->expectException(SyndicationRefused::class);
        $this->expectExceptionMessageIsOrContains('The partner reseller is not a supplier.');

        $this->receive($this->resellerSide, 'reseller', Partners::resellerKey(), 'msg_1', 'shipment.sent', ['order' => 'R-77', 'shipment' => 'SH-1', 'lines' => [['sku' => 'TEA-1', 'quantity' => 1]]]);
    }

    public function testAnUnreadableParcelIsRefused(): void
    {
        $this->expectException(SyndicationRefused::class);
        $this->expectExceptionMessageIsOrContains('The "shipment.sent" message cannot be read: "carrier" is a string or null.');

        $this->receive($this->resellerSide, 'supplier', Partners::supplierKey(), 'msg_1', 'shipment.sent', ['order' => 'R-77', 'shipment' => 'SH-1', 'carrier' => 7, 'lines' => [['sku' => 'TEA-1', 'quantity' => 1]]]);
    }

    public function testAReturnIsRequestedReceivedAndCreditedOnBothSides(): void
    {
        $returns = $this->returns($this->resellerSide);
        $return = $returns->request('supplier', 'R-77', 'RET-1', ['TEA-1' => 2, 'MUG' => 1], $this->at, 'Canada Post', 'CP999');

        self::assertSame([ReturnState::Requested, 'CAD', ['TEA-1' => 2, 'MUG' => 1]], [$return->state(), $return->currency()->code, $return->requested]);
        $requested = $this->resellerSide->outbox[0];
        self::assertSame(['return.requested', ['order' => 'R-77', 'return' => 'RET-1', 'currency' => 'CAD', 'carrier' => 'Canada Post', 'tracking' => 'CP999', 'lines' => [['sku' => 'TEA-1', 'quantity' => 2], ['sku' => 'MUG', 'quantity' => 1]]]], [$requested->type, $requested->data]);

        $this->deliver($requested, Partners::resellerKey(), 'reseller', $this->supplierSide);
        $this->deliver($requested, Partners::resellerKey(), 'reseller', $this->supplierSide, 'msg_again');
        self::assertSame(['requested RET-1'], $this->supplierHandler->calls);
        self::assertSame(['CP999'], array_map(static fn (PartnerReturn $return): ?string => $return->tracking, $this->returns($this->supplierSide)->returnsOf('reseller', 'R-77')));

        $received = $this->returns($this->supplierSide)->receive('reseller', 'RET-1', ['TEA-1' => 1], $this->at->modify('+3 days'));
        self::assertSame([ReturnState::Received, ['TEA-1' => 1], 700, '2026-11-15'], [$received->state(), $received->received(), $received->credit?->minorUnits, $received->receivedAt()?->format('Y-m-d')]);
        $answer = $this->supplierSide->outbox[0];
        self::assertSame(['return.received', ['order' => 'R-77', 'return' => 'RET-1', 'currency' => 'CAD', 'credit' => 700, 'lines' => [['sku' => 'TEA-1', 'quantity' => 1]]]], [$answer->type, $answer->data]);

        $this->deliver($answer, Partners::supplierKey(), 'supplier', $this->resellerSide);
        self::assertSame(['received RET-1'], $this->resellerHandler->calls);
        self::assertSame([ReturnState::Received, 700], [$return->state(), $return->credit?->minorUnits]);

        self::assertSame(1_800, new Ledger($this->supplierSide)->balance('reseller', Currency::of('CAD'))->minorUnits);
        self::assertSame(-1_800, new Ledger($this->resellerSide)->balance('supplier', Currency::of('CAD'))->minorUnits);
        $credit = $this->supplierSide->entriesOf('reseller', 'R-77')[1];
        self::assertSame(['RET-1', -700, [['sku' => 'TEA-1', 'quantity' => 1, 'unit_cost' => 700]]], [$credit->returnNumber, $credit->amount->minorUnits, $credit->lines]);
        self::assertNull($this->supplierSide->entriesOf('reseller', 'R-77')[0]->returnNumber);
        $order = $this->supplierSide->findEntry('reseller', 'R-77');
        self::assertNotNull($order);
        self::assertSame(['R-77', null, 2_500], [$order->orderNumber, $order->returnNumber, $order->amount->minorUnits]);
    }

    public function testNoMoreIsReturnedThanTheOrderHoldsLessEarlierReturns(): void
    {
        $returns = $this->returns($this->resellerSide);
        $returns->request('supplier', 'R-77', 'RET-1', ['TEA-1' => 1], $this->at);

        try {
            $returns->request('supplier', 'R-77', 'RET-2', ['TEA-1' => 2], $this->at);
            self::fail('More was returned than the order held.');
        } catch (SyndicationRefused $syndicationRefused) {
            self::assertSame('2 of TEA-1 cannot be returned against R-77: 1 are left.', $syndicationRefused->getMessage());
        }

        try {
            $returns->request('supplier', 'R-77', 'RET-1', ['MUG' => 1], $this->at);
            self::fail('A return number was used twice.');
        } catch (SyndicationRefused $syndicationRefused) {
            self::assertSame('The return RET-1 is already requested.', $syndicationRefused->getMessage());
        }

        try {
            $returns->request('supplier', 'R-77', 'RET-3', ['MAP' => 1], $this->at);
            self::fail('A SKU the order does not hold was returned.');
        } catch (SyndicationRefused $syndicationRefused) {
            self::assertSame('1 of MAP cannot be returned against R-77: 0 are left.', $syndicationRefused->getMessage());
        }

        $this->expectException(SyndicationRefused::class);
        $this->expectExceptionMessageIsOrContains('No order R-404 is in the ledger with supplier.');

        $returns->request('supplier', 'R-404', 'RET-4', ['TEA-1' => 1], $this->at);
    }

    public function testASupplierRefusesAReturnBeyondTheOrderOrInAnotherCurrency(): void
    {
        try {
            $this->receive($this->supplierSide, 'reseller', Partners::resellerKey(), 'msg_1', 'return.requested', ['order' => 'R-77', 'return' => 'RET-1', 'currency' => 'CAD', 'lines' => [['sku' => 'MUG', 'quantity' => 2]]]);
            self::fail('More was returned than the order held.');
        } catch (SyndicationRefused $syndicationRefused) {
            self::assertSame('2 of MUG cannot be returned against R-77: 1 are left.', $syndicationRefused->getMessage());
        }

        try {
            $this->receive($this->supplierSide, 'reseller', Partners::resellerKey(), 'msg_2', 'return.requested', ['order' => 'R-77', 'return' => 'RET-1', 'currency' => 'USD', 'lines' => [['sku' => 'MUG', 'quantity' => 1]]]);
            self::fail('A return in another currency was taken.');
        } catch (SyndicationRefused $syndicationRefused) {
            self::assertSame('The "return.requested" message cannot be read: its currency is the order\'s.', $syndicationRefused->getMessage());
        }

        $this->expectException(SyndicationRefused::class);
        $this->expectExceptionMessageIsOrContains('The partner supplier is not a reseller.');

        $this->supplierSide->addPartner(Partners::supplier());
        $this->receive($this->supplierSide, 'supplier', Partners::supplierKey(), 'msg_3', 'return.requested', ['order' => 'R-77', 'return' => 'RET-1', 'currency' => 'CAD', 'lines' => [['sku' => 'MUG', 'quantity' => 1]]]);
    }

    public function testAReturnIsReceivedOnceAndNeverBeyondWhatWasAnnounced(): void
    {
        $this->returns($this->resellerSide)->request('supplier', 'R-77', 'RET-1', ['TEA-1' => 1], $this->at);
        $this->deliver($this->resellerSide->outbox[0], Partners::resellerKey(), 'reseller', $this->supplierSide);
        $returns = $this->returns($this->supplierSide);

        try {
            $returns->receive('reseller', 'RET-1', ['TEA-1' => 2], $this->at);
            self::fail('More was received than announced.');
        } catch (SyndicationRefused $syndicationRefused) {
            self::assertSame('2 of TEA-1 cannot be returned against RET-1: 1 are left.', $syndicationRefused->getMessage());
        }

        $returns->receive('reseller', 'RET-1', null, $this->at);

        try {
            $returns->receive('reseller', 'RET-1', null, $this->at);
            self::fail('A return was received twice.');
        } catch (SyndicationRefused $syndicationRefused) {
            self::assertSame('The return RET-1 is already received.', $syndicationRefused->getMessage());
        }

        $this->expectException(SyndicationRefused::class);
        $this->expectExceptionMessageIsOrContains('No return RET-9 is known with reseller.');

        $returns->receive('reseller', 'RET-9', null, $this->at);
    }

    public function testAResellerRefusesACreditItsLedgerDoesNotGive(): void
    {
        $this->returns($this->resellerSide)->request('supplier', 'R-77', 'RET-1', ['TEA-1' => 2], $this->at);

        try {
            $this->receive($this->resellerSide, 'supplier', Partners::supplierKey(), 'msg_1', 'return.received', ['order' => 'R-77', 'return' => 'RET-1', 'currency' => 'CAD', 'credit' => 2_000, 'lines' => [['sku' => 'TEA-1', 'quantity' => 2]]]);
            self::fail('A credit the ledger does not give was taken.');
        } catch (SyndicationRefused $syndicationRefused) {
            self::assertSame('The return RET-1 is credited 20.00 by the supplier and 14.00 by the ledger here.', $syndicationRefused->getMessage());
        }

        $this->expectException(SyndicationRefused::class);
        $this->expectExceptionMessageIsOrContains('No return RET-9 is known with supplier.');

        $this->receive($this->resellerSide, 'supplier', Partners::supplierKey(), 'msg_2', 'return.received', ['order' => 'R-77', 'return' => 'RET-9', 'currency' => 'CAD', 'credit' => 0, 'lines' => [['sku' => 'TEA-1', 'quantity' => 1]]]);
    }

    public function testAReturnRecordsACreditInItsCurrencyOnly(): void
    {
        $return = PartnerReturn::request(Partners::supplier(), 'RET-1', 'R-77', Currency::of('CAD'), ['TEA-1' => 1], $this->at);

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The credit of the return RET-1 is zero or more, in its currency.');

        try {
            $return->receive(['MUG' => 1], Money::zero(Currency::of('CAD')), $this->at);
            self::fail('A SKU not announced was received.');
        } catch (SyndicationRefused) {
        }

        $return->receive(['TEA-1' => 1], Money::of(700, Currency::of('USD')), $this->at);
    }

    public function testLinesAreBounded(): void
    {
        foreach ([[], ['TEA 1' => 1], ['TEA-1' => 0], array_fill_keys(array_map(static fn (int $sku): string => 'SKU-'.$sku, range(1, 501)), 1)] as $lines) {
            try {
                PartnerShipment::of(Partners::supplier(), 'SH-1', 'R-77', $lines, $this->at);
                self::fail('Lines out of bounds were taken.');
            } catch (InvalidValue $invalid) {
                self::assertStringStartsWith('A shipment or a return', $invalid->getMessage());
            }
        }

        foreach ([['SH 1', 'R-77', null, null], ['SH-1', 'R 77', null, null], ['SH-1', 'R-77', "Canada\nPost", null], ['SH-1', 'R-77', null, 'CP 1'], ["SH-1\n", 'R-77', null, null], ['SH-1', "R-77\n", null, null], ['SH-1', 'R-77', "Canada Post\n", null], ['SH-1', 'R-77', null, "CP1\n"]] as [$shipment, $order, $carrier, $tracking]) {
            try {
                PartnerShipment::of(Partners::supplier(), $shipment, $order, ['TEA-1' => 1], $this->at, $carrier, $tracking);
                self::fail('A field out of bounds was taken.');
            } catch (InvalidValue) {
            }

            try {
                PartnerReturn::request(Partners::supplier(), $shipment, $order, Currency::of('CAD'), ['TEA-1' => 1], $this->at, $carrier, $tracking);
                self::fail('A field out of bounds was taken.');
            } catch (InvalidValue) {
            }
        }

        self::assertCount(500, PartnerShipment::of(Partners::supplier(), 'SH-1', 'R-77', array_fill_keys(array_map(static fn (int $sku): string => 'SKU-'.$sku, range(1, 500)), 1), $this->at)->lines);
    }

    public function testLinesOfAMessageAreAListOfObjects(): void
    {
        foreach ([['lines' => 'x'], ['lines' => ['a' => []]], ['lines' => ['x']], ['lines' => [['sku' => 1, 'quantity' => 1]]]] as $data) {
            try {
                new MessageData($data)->quantities();
                self::fail('Unreadable lines were read.');
            } catch (InvalidValue) {
            }
        }

        self::assertSame(['TEA-1' => 3], new MessageData(['lines' => [['sku' => 'TEA-1', 'quantity' => 1], ['sku' => 'TEA-1', 'quantity' => 2]]])->quantities());
        self::assertNull(new MessageData([])->optionalString('carrier'));
    }

    public function testALineOfAnotherShapeIsRefusedWhateverItsEnd(): void
    {
        foreach ([["TEA-1\n" => 1], [str_repeat('t', 65) => 1]] as $lines) {
            foreach ([static fn () => PartnerShipment::checkLines($lines), fn (): PartnerReturn => PartnerReturn::request(Partners::supplier(), 'RET-1', 'R-77', Currency::of('CAD'), $lines, $this->at)] as $take) {
                try {
                    $take();
                    self::fail('A SKU of another shape was taken.');
                } catch (InvalidValue $invalid) {
                    self::assertStringStartsWith('A shipment or a return', $invalid->getMessage());
                }
            }
        }

        $this->expectException(InvalidValue::class);
        PartnerReturn::request(Partners::supplier(), 'RET-1', 'R-77', Currency::of('CAD'), [], $this->at);
    }

    public function testAReturnReceivedNamesLinesAndNeverMoreThanAnnounced(): void
    {
        $return = PartnerReturn::request(Partners::supplier(), 'RET-1', 'R-77', Currency::of('CAD'), ['TEA-1' => 1], $this->at);

        try {
            $return->receive([], Money::zero(Currency::of('CAD')), $this->at);
            self::fail('A return was received without a line.');
        } catch (InvalidValue $invalidValue) {
            self::assertStringStartsWith('A shipment or a return lists', $invalidValue->getMessage());
        }

        try {
            $return->receive(['MUG' => 2], Money::zero(Currency::of('CAD')), $this->at);
            self::fail('A SKU not announced was received.');
        } catch (SyndicationRefused $syndicationRefused) {
            self::assertSame('2 of MUG cannot be returned against RET-1: 0 are left.', $syndicationRefused->getMessage());
        }

        try {
            $return->receive(['MUG' => 1], Money::zero(Currency::of('CAD')), $this->at);
            self::fail('A SKU not announced was received.');
        } catch (SyndicationRefused) {
        }

        $return->receive(['TEA-1' => 1], Money::of(700, Currency::of('CAD')), $this->at);
        self::assertSame(ReturnState::Received, $return->state());
    }

    public function testAReturnReceivedFromAResellerIsRefused(): void
    {
        $this->resellerSide->addPartner(Partners::reseller('reseller', ['TEA-1']));

        $this->expectException(SyndicationRefused::class);
        $this->expectExceptionMessageIsOrContains('The partner reseller is not a supplier.');

        $this->receive($this->resellerSide, 'reseller', Partners::resellerKey(), 'msg_1', 'return.received', ['order' => 'R-77', 'return' => 'RET-1', 'currency' => 'CAD', 'credit' => 700, 'lines' => [['sku' => 'TEA-1', 'quantity' => 1]]]);
    }

    public function testAnUnknownOrSuspendedPartnerAndAWrongRoleAreRefused(): void
    {
        $suspended = Partners::supplier('paused');
        $suspended->suspend();

        $this->resellerSide->addPartner($suspended);

        foreach (['nobody', 'paused'] as $code) {
            try {
                $this->returns($this->resellerSide)->request($code, 'R-77', 'RET-1', ['TEA-1' => 1], $this->at);
                self::fail('A partner out of reach was asked.');
            } catch (UnknownPartner) {
            }
        }

        $roles = [
            [fn (): PartnerReturn => $this->returns($this->supplierSide)->request('reseller', 'R-77', 'RET-1', ['TEA-1' => 1], $this->at), 'The partner reseller is not a supplier.'],
            [fn (): PartnerReturn => $this->returns($this->resellerSide)->receive('supplier', 'RET-1', null, $this->at), 'The partner supplier is not a reseller.'],
            [fn (): bool => $this->tracking($this->resellerSide)->notify('supplier', 'R-77', 'SH-1', ['TEA-1' => 1], $this->at), 'The partner supplier is not a reseller.'],
        ];

        foreach ($roles as [$act, $message]) {
            try {
                $act();
                self::fail('A partner of another role was dealt with.');
            } catch (SyndicationRefused $syndicationRefused) {
                self::assertSame($message, $syndicationRefused->getMessage());
            }
        }
    }

    public function testEveryEarlierReturnCountsAgainstWhatIsLeft(): void
    {
        $returns = $this->returns($this->resellerSide);
        $returns->request('supplier', 'R-77', 'RET-1', ['TEA-1' => 1], $this->at);
        $returns->request('supplier', 'R-77', 'RET-2', ['TEA-1' => 1], $this->at);

        $this->expectException(SyndicationRefused::class);
        $this->expectExceptionMessageIsOrContains('1 of TEA-1 cannot be returned against R-77: 0 are left.');

        $returns->request('supplier', 'R-77', 'RET-3', ['TEA-1' => 1], $this->at);
    }

    public function testANumericSkuTravelsAsText(): void
    {
        $lines = [['sku' => '1001', 'quantity' => 2, 'unit_cost' => 300]];
        $this->resellerSide->record(LedgerEntry::of(Partners::supplier(), 'R-88', Money::of(-600, Currency::of('CAD')), $this->at, $lines));
        $this->supplierSide->record(LedgerEntry::of(Partners::reseller('reseller', ['1001']), 'R-88', Money::of(600, Currency::of('CAD')), $this->at, $lines));

        $this->tracking($this->supplierSide)->notify('reseller', 'R-88', 'SH-7', ['1001' => 1], $this->at);
        self::assertSame([['sku' => '1001', 'quantity' => 1]], $this->supplierSide->outbox[0]->data['lines']);

        $this->returns($this->resellerSide)->request('supplier', 'R-88', 'RET-7', ['1001' => 2], $this->at);
        $this->deliver($this->resellerSide->outbox[0], Partners::resellerKey(), 'reseller', $this->supplierSide);
        $this->returns($this->supplierSide)->receive('reseller', 'RET-7', null, $this->at);

        $credit = $this->supplierSide->entriesOf('reseller', 'R-88')[1];
        self::assertSame([['sku' => '1001', 'quantity' => 2, 'unit_cost' => 300]], $credit->lines);
    }

    public function testAParcelOfAnUnknownOrderIsRefusedWhenItArrives(): void
    {
        $this->expectException(SyndicationRefused::class);
        $this->expectExceptionMessageIsOrContains('No order R-404 is in the ledger with supplier.');

        $this->tracking($this->resellerSide)->received(Partners::supplier(), ['order' => 'R-404', 'shipment' => 'SH-9', 'lines' => [['sku' => 'TEA-1', 'quantity' => 1]]], $this->at);
    }

    public function testWithoutTrackingNorReturnsTheirMessagesAreReceivedAndIgnored(): void
    {
        $inbox = new Inbox($this->resellerSide, $this->resellerSide, $this->resellerSide, $this->resellerSide, $this->resellerSide, 'reseller', 300);

        foreach ([
            ['shipment.sent', ['order' => 'R-77', 'shipment' => 'SH-1', 'lines' => [['sku' => 'TEA-1', 'quantity' => 1]]]],
            ['return.requested', ['order' => 'R-77', 'return' => 'RET-1', 'currency' => 'CAD', 'lines' => [['sku' => 'TEA-1', 'quantity' => 1]]]],
            ['return.received', ['order' => 'R-77', 'return' => 'RET-1', 'currency' => 'CAD', 'credit' => 700, 'lines' => [['sku' => 'TEA-1', 'quantity' => 1]]]],
        ] as $number => [$type, $data]) {
            $body = json_encode(['type' => $type, 'timestamp' => $this->at->format(OutgoingMessage::MOMENT), 'audience' => 'reseller', 'data' => $data], \JSON_THROW_ON_ERROR);
            $headers = new Signer()->sign(WebhookId::fromString('msg_'.$number), WebhookTimestamp::fromDateTime($this->at), $body, Partners::supplierKey());

            self::assertTrue($inbox->receive('supplier', WebhookHeaders::fromStrings(...array_values($headers->toArray())), $body, $this->at));
        }

        self::assertSame([], $this->resellerSide->shipments);
        self::assertSame([], $this->resellerHandler->calls);
    }

    public function testACreditThatIsNotAnIntegerCannotBeRead(): void
    {
        $this->returns($this->resellerSide)->request('supplier', 'R-77', 'RET-1', ['TEA-1' => 1], $this->at);

        $this->expectException(SyndicationRefused::class);
        $this->expectExceptionMessageIsOrContains('The "return.received" message cannot be read: "credit" is an integer.');

        $this->receive($this->resellerSide, 'supplier', Partners::supplierKey(), 'msg_1', 'return.received', ['order' => 'R-77', 'return' => 'RET-1', 'currency' => 'CAD', 'credit' => '700', 'lines' => [['sku' => 'TEA-1', 'quantity' => 1]]]);
    }

    public function testACreditForASkuTheOrderDoesNotHoldIsRefused(): void
    {
        $this->returns($this->resellerSide)->request('supplier', 'R-77', 'RET-1', ['TEA-1' => 1], $this->at);

        $this->expectException(SyndicationRefused::class);
        $this->expectExceptionMessageIsOrContains('The order R-77 holds no MAP.');

        $this->receive($this->resellerSide, 'supplier', Partners::supplierKey(), 'msg_1', 'return.received', ['order' => 'R-77', 'return' => 'RET-1', 'currency' => 'CAD', 'credit' => 0, 'lines' => [['sku' => 'MAP', 'quantity' => 1]]]);
    }

    public function testLinesOfAMessageAreSummedPerSku(): void
    {
        self::assertSame(['TEA-1' => 1, 'MUG' => 2], new MessageData(['lines' => [['sku' => 'TEA-1', 'quantity' => 1], ['sku' => 'MUG', 'quantity' => 2]]])->quantities());
    }

    private function tracking(InMemorySyndication $store): ShipmentTracking
    {
        return new ShipmentTracking($store, $store, $store, $store, [$store === $this->supplierSide ? $this->supplierHandler : $this->resellerHandler]);
    }

    private function returns(InMemorySyndication $store): PartnerReturns
    {
        return new PartnerReturns($store, $store, $store, $store, [$store === $this->supplierSide ? $this->supplierHandler : $this->resellerHandler]);
    }

    private function deliver(OutgoingMessage $message, Ed25519SecretKey $key, string $from, InMemorySyndication $to, ?string $id = null): void
    {
        $body = $message->body();
        $headers = new Signer()->sign(WebhookId::fromString($id ?? $message->id), WebhookTimestamp::fromDateTime($this->at), $body, $key);
        $this->inbox($to)->receive($from, WebhookHeaders::fromStrings(...array_values($headers->toArray())), $body, $this->at);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function receive(InMemorySyndication $store, string $from, Ed25519SecretKey $key, string $id, string $type, array $data): void
    {
        $audience = $store === $this->supplierSide ? 'supplier' : 'reseller';
        $body = json_encode(['type' => $type, 'timestamp' => $this->at->format(OutgoingMessage::MOMENT), 'audience' => $audience, 'data' => $data], \JSON_THROW_ON_ERROR);
        $headers = new Signer()->sign(WebhookId::fromString($id), WebhookTimestamp::fromDateTime($this->at), $body, $key);
        $this->inbox($store)->receive($from, WebhookHeaders::fromStrings(...array_values($headers->toArray())), $body, $this->at);
    }

    private function inbox(InMemorySyndication $store): Inbox
    {
        // Each side's identity is the code its partner knows it by.
        return new Inbox($store, $store, $store, $store, $store, $store === $this->supplierSide ? 'supplier' : 'reseller', 300, tracking: $this->tracking($store), returns: $this->returns($store));
    }
}
