<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CommerceSyndicationBundle\Application\Courier;
use Phil\CommerceSyndicationBundle\Application\Ledger;
use Phil\CommerceSyndicationBundle\Application\MessageRetention;
use Phil\CommerceSyndicationBundle\Application\PartnerReturns;
use Phil\CommerceSyndicationBundle\Application\Partners;
use Phil\CommerceSyndicationBundle\Application\Publisher;
use Phil\CommerceSyndicationBundle\Application\ShipmentTracking;
use Phil\CommerceSyndicationBundle\Domain\Model\LedgerEntry;
use Phil\CommerceSyndicationBundle\Domain\Model\OutgoingMessage;
use Phil\CommerceSyndicationBundle\Domain\Model\Partner;
use Phil\CommerceSyndicationBundle\Domain\Model\PartnerReturn;
use Phil\CommerceSyndicationBundle\Domain\Model\PartnerShipment;
use Phil\CommerceSyndicationBundle\Domain\Model\PublishedPrice;
use Phil\CommerceSyndicationBundle\Domain\Model\ReceivedMessage;
use Phil\CommerceSyndicationBundle\Domain\Partner\PartnerRole;
use Phil\CommerceSyndicationBundle\Domain\Repository\LedgerRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\MessageRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\PartnerRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\PublishedPriceRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\SyndicatedProductRepository;
use Phil\CommerceSyndicationBundle\Infrastructure\Http\SyndicationWebhookController;
use Phil\CommerceSyndicationBundle\PhilCommerceSyndicationBundle;
use Phil\CommerceSyndicationBundle\Tests\Functional\App\TestKernel;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\Partners as Keys;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\StandardWebhooks\Signer;
use Phil\StandardWebhooks\WebhookId;
use Phil\StandardWebhooks\WebhookTimestamp;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(PhilCommerceSyndicationBundle::class)]
final class SyndicationFlowTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
        $this->entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        new SchemaTool($this->entityManager)->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
        $partners = $this->service(Partners::class, Partners::class);
        $partners->register('supplier', 'The supplier', PartnerRole::Supplier, 'https://supplier.example/in', Keys::supplierKey()->publicKey()->toString());
        $partners->register('reseller', 'The reseller', PartnerRole::Reseller, 'https://reseller.example/in', Keys::resellerKey()->publicKey()->toString());
        $partners->register('idle', 'The idle reseller', PartnerRole::Reseller, 'https://idle.example/in', Keys::resellerKey()->publicKey()->toString())->suspend();
        $partners->feed('reseller', ['TEA-1'], 300_000);
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    public function testTheFeedIsQueuedForTheActiveResellers(): void
    {
        $repository = $this->service(PartnerRepository::class, PartnerRepository::class);
        self::assertSame(['reseller'], array_map(static fn (Partner $partner): string => $partner->code, $repository->activeResellers()));
        self::assertSame(['TEA-1'], $repository->findPartner('reseller')?->skus());
        self::assertSame(['reseller'], array_map(static fn (Partner $partner): string => $partner->code, $repository->resellersFed('TEA-1')), 'the index is kept by the listener the bundle registers');

        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $this->service(Publisher::class, Publisher::class)->publish('TEA-1', 'Green tea', Money::of(1_000, Currency::of('CAD')), 'food', 4, true, $at);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $messages = $this->service(MessageRepository::class, MessageRepository::class);
        self::assertSame([], $messages->due($at->modify('-1 second'), 10));
        $due = $messages->due($at, 10);
        self::assertCount(1, $due);
        self::assertSame(['reseller', 700], [$due[0]->partner, $due[0]->data['cost']]);
        $this->service(Courier::class, Courier::class);

        $prices = $this->service(PublishedPriceRepository::class, PublishedPriceRepository::class);
        self::assertSame(700, $prices->latestPrice('reseller', 'TEA-1', 'CAD')?->cost->minorUnits);
        self::assertSame([700], array_map(static fn (PublishedPrice $price): int => $price->cost->minorUnits, $prices->pricesInEffectSince('reseller', ['TEA-1'], 'CAD', $at->modify('+1 day'))['TEA-1']));
    }

    /**
     * Two runs claim the same message: one wins. The claim is atomic in the database.
     */
    public function testAMessageIsClaimedByOneRunOnly(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $this->service(Publisher::class, Publisher::class)->publish('TEA-1', 'Green tea', Money::of(1_000, Currency::of('CAD')), 'food', 4, true, $at);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $messages = $this->service(MessageRepository::class, MessageRepository::class);
        [$message] = $messages->due($at, 10);
        self::assertTrue($messages->claim($message, $at, $at->modify('+30 minutes')));
        self::assertFalse($messages->claim($message, $at->modify('+1 minute'), $at->modify('+31 minutes')));
        self::assertSame([], $messages->due($at->modify('+1 minute'), 10));
        self::assertCount(1, $messages->due($at->modify('+30 minutes'), 10));
    }

    /**
     * The prices in effect since a moment: the last one published before it, and every one since.
     */
    public function testThePricesInEffectAreReadBack(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $publisher = $this->service(Publisher::class, Publisher::class);

        foreach ([[1_000, '-20 days'], [1_100, '-10 days'], [1_200, '-2 days'], [1_300, '-1 day']] as [$retail, $when]) {
            $publisher->publish('TEA-1', 'Green tea', Money::of($retail, Currency::of('CAD')), 'food', 4, true, $at->modify($when));
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        $prices = $this->service(PublishedPriceRepository::class, PublishedPriceRepository::class);
        self::assertSame([770, 840, 910], array_map(static fn (PublishedPrice $price): int => $price->cost->minorUnits, $prices->pricesInEffectSince('reseller', ['TEA-1'], 'CAD', $at->modify('-7 days'))['TEA-1']));
        self::assertSame([], $prices->pricesInEffectSince('reseller', ['TEA-1'], 'USD', $at));
        self::assertSame(910, $prices->latestPrice('reseller', 'TEA-1', 'CAD')?->cost->minorUnits);
    }

    /**
     * Messages past the retention are deleted; those still waiting, or recent, are kept.
     */
    public function testTheRetentionPurgesOldMessages(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $repository = $this->service(PartnerRepository::class, PartnerRepository::class);
        $reseller = $repository->findPartner('reseller');
        $supplier = $repository->findPartner('supplier');
        self::assertNotNull($reseller);
        self::assertNotNull($supplier);
        $messages = $this->service(MessageRepository::class, MessageRepository::class);
        $old = OutgoingMessage::to($reseller, 'product.updated', [], $at->modify('-100 days'));
        $old->delivered($at->modify('-95 days'));

        $givenUp = OutgoingMessage::to($reseller, 'product.updated', [], $at->modify('-100 days'));
        $givenUp->refused($at->modify('-91 days'), 'HTTP 400.');

        $recent = OutgoingMessage::to($reseller, 'product.updated', [], $at->modify('-100 days'));
        $recent->delivered($at->modify('-89 days'));

        $waiting = OutgoingMessage::to($reseller, 'product.updated', [], $at->modify('-100 days'));

        foreach ([$old, $givenUp, $recent, $waiting] as $message) {
            $messages->queue($message);
        }

        $messages->markReceived(ReceivedMessage::of('msg_old', $supplier, 'product.updated', $at->modify('-91 days')));
        $messages->markReceived(ReceivedMessage::of('msg_new', $supplier, 'product.updated', $at->modify('-89 days')));

        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame([2, 1], $this->service(MessageRetention::class, MessageRetention::class)->purge($at));
        self::assertFalse($messages->wasReceived('supplier', 'msg_old'));
        self::assertTrue($messages->wasReceived('supplier', 'msg_new'));
        self::assertSame([$waiting->id], array_map(static fn (OutgoingMessage $message): string => $message->id, $messages->due($at, 10)));
        self::assertNotNull($this->entityManager->find(OutgoingMessage::class, $recent->id));
    }

    public function testASuppliersMessageIsReceivedOnce(): void
    {
        $body = '{"type":"product.updated","timestamp":"2026-11-03T09:00:00.000000+00:00","audience":"shop","data":{"sku":"TEA-9","name":"Tea","currency":"CAD","retail":900,"cost":600,"tax_category":"food","available":2,"active":true}}';
        $controller = $this->service(SyndicationWebhookController::class, SyndicationWebhookController::class);
        $now = new DateTimeImmutable();

        self::assertSame(204, $controller($this->request('msg_1', $body, $now), 'supplier')->getStatusCode());
        $this->entityManager->clear();
        self::assertSame(204, $controller($this->request('msg_1', $body, $now), 'supplier')->getStatusCode());

        self::assertTrue($this->service(MessageRepository::class, MessageRepository::class)->wasReceived('supplier', 'msg_1'));
        self::assertFalse($this->service(MessageRepository::class, MessageRepository::class)->wasReceived('reseller', 'msg_1'));
        self::assertFalse($this->service(MessageRepository::class, MessageRepository::class)->wasReceived('supplier', 'msg_2'));
        self::assertSame(600, $this->service(SyndicatedProductRepository::class, SyndicatedProductRepository::class)->findProduct('supplier', 'TEA-9')?->cost->minorUnits);
    }

    public function testTheLedgerIsSettled(): void
    {
        $ledger = $this->service(LedgerRepository::class, LedgerRepository::class);
        $partner = $this->service(PartnerRepository::class, PartnerRepository::class)->findPartner('supplier');
        self::assertNotNull($partner);
        $at = new DateTimeImmutable('2026-11-03 10:00:00');
        $ledger->record(LedgerEntry::of($partner, 'A-1', Money::of(-700, Currency::of('CAD')), $at));
        $ledger->record(LedgerEntry::of($partner, 'A-2', Money::of(-300, Currency::of('CAD')), $at->modify('+1 hour')));

        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame('A-2', $ledger->findEntry('supplier', 'A-2')?->orderNumber);
        self::assertNull($ledger->findEntry('reseller', 'A-2'));
        self::assertSame(['A-1', 'A-2'], array_map(static fn (LedgerEntry $entry): string => $entry->orderNumber, $ledger->openEntries('supplier')));

        self::assertSame(2, $this->service(Ledger::class, Ledger::class)->settle('supplier', Currency::of('CAD'), $at));
        $this->entityManager->flush();
        $this->entityManager->clear();
        self::assertSame([], $ledger->openEntries('supplier'));
    }

    /**
     * A parcel and a return of a forwarded order, with the return's credit, stored and read back; the parcel's moment
     * kept in UTC whatever the zone of the process.
     */
    public function testParcelsReturnsAndCreditsAreStored(): void
    {
        $zone = date_default_timezone_get();
        date_default_timezone_set('America/Toronto');

        try {
            $ledger = $this->service(LedgerRepository::class, LedgerRepository::class);
            $reseller = $this->service(PartnerRepository::class, PartnerRepository::class)->findPartner('reseller');
            self::assertNotNull($reseller);
            $at = new DateTimeImmutable('2026-11-12 22:30:00');
            $ledger->record(LedgerEntry::of($reseller, 'R-77', Money::of(2_100, Currency::of('CAD')), $at, [['sku' => 'TEA-1', 'quantity' => 3, 'unit_cost' => 700]]));
            $this->entityManager->flush();

            self::assertTrue($this->service(ShipmentTracking::class, ShipmentTracking::class)->notify('reseller', 'R-77', 'SH-1', ['TEA-1' => 3], $at, 'Canada Post', 'CP123'));
            $this->entityManager->flush();
            $this->entityManager->clear();

            $returns = $this->service(PartnerReturns::class, PartnerReturns::class);
            $reseller = $this->service(PartnerRepository::class, PartnerRepository::class)->findPartner('reseller');
            self::assertNotNull($reseller);
            $returns->requested($reseller, ['order' => 'R-77', 'return' => 'RET-1', 'currency' => 'CAD', 'lines' => [['sku' => 'TEA-1', 'quantity' => 2]]], $at);
            $this->entityManager->flush();
            $this->entityManager->clear();
            $returns->receive('reseller', 'RET-1', ['TEA-1' => 1], $at->modify('+1 day'));
            $this->entityManager->flush();
            $this->entityManager->clear();

            $shipments = $this->service(ShipmentTracking::class, ShipmentTracking::class)->shipmentsOf('reseller', 'R-77');
            self::assertSame([['SH-1', 'CP123', '2026-11-13 03:30:00 UTC']], array_map(static fn (PartnerShipment $shipment): array => [$shipment->shipment, $shipment->tracking, $shipment->shippedAt->format('Y-m-d H:i:s T')], $shipments));
            self::assertSame([['RET-1', 700, ['TEA-1' => 1]]], array_map(static fn (PartnerReturn $return): array => [$return->returnNumber, $return->credit?->minorUnits, $return->received()], $returns->returnsOf('reseller', 'R-77')));
            self::assertSame([[null, 2_100], ['RET-1', -700]], array_map(static fn (LedgerEntry $entry): array => [$entry->returnNumber, $entry->amount->minorUnits], $ledger->entriesOf('reseller', 'R-77')));
            self::assertNull($ledger->findEntry('reseller', 'R-77')?->returnNumber);
            self::assertSame(['shipment.sent', 'return.received'], array_map(static fn (OutgoingMessage $message): string => $message->type, $this->entityManager->getRepository(OutgoingMessage::class)->findBy([], ['createdAt' => 'ASC'])));
        } finally {
            date_default_timezone_set($zone);
        }
    }

    private function request(string $id, string $body, DateTimeImmutable $at): Request
    {
        $server = [];

        foreach (new Signer()->sign(WebhookId::fromString($id), WebhookTimestamp::fromDateTime($at), $body, Keys::supplierKey())->toArray() as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return Request::create('/syndication/supplier', 'POST', server: $server, content: $body);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $id, string $class): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
