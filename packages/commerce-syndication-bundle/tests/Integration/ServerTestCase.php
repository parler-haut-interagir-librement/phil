<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Tests\Integration;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CommerceSyndicationBundle\Domain\Model\LedgerEntry;
use Phil\CommerceSyndicationBundle\Domain\Model\OutgoingMessage;
use Phil\CommerceSyndicationBundle\Domain\Model\PartnerReturn;
use Phil\CommerceSyndicationBundle\Domain\Model\PartnerShipment;
use Phil\CommerceSyndicationBundle\Domain\Model\PublishedPrice;
use Phil\CommerceSyndicationBundle\Infrastructure\Doctrine\DoctrineSyndicationRepository;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\Partners;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\TestCase;

/**
 * The module's mapping and queries on a real server, read from the environment: moments written and compared in UTC
 * from a process on another zone, the claim of a message, the purge, the prices in effect, the ledger's entries of an
 * order and of its returns, the parcels and returns read back.
 */
abstract class ServerTestCase extends TestCase
{
    private EntityManager $entityManager;

    private DoctrineSyndicationRepository $repository;

    private string $zone;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    #[Override]
    protected function setUp(): void
    {
        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        $this->zone = date_default_timezone_get();
        date_default_timezone_set('America/Toronto');
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 2).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $parameters = new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url);
        $this->entityManager = new EntityManager(DriverManager::getConnection($parameters), $configuration);
        $tool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        $this->repository = new DoctrineSyndicationRepository($this->entityManager);
    }

    #[Override]
    protected function tearDown(): void
    {
        if (isset($this->zone)) {
            date_default_timezone_set($this->zone);
        }

        if (isset($this->entityManager)) {
            $this->entityManager->getConnection()->close();
        }
    }

    public function testMessagesAreClaimedAndPurgedInUtc(): void
    {
        $reseller = Partners::reseller();
        $this->repository->addPartner($reseller);
        $at = new DateTimeImmutable('2026-11-12 22:30:00');
        $message = OutgoingMessage::to($reseller, 'product.updated', ['sku' => 'TEA-1'], $at);
        $this->repository->queue($message);
        $this->entityManager->flush();

        self::assertSame('2026-11-13 03:30:00', $this->entityManager->getConnection()->fetchOne('SELECT created_at FROM phil_commerce_syndication_outgoing'));
        self::assertSame([$message->id], array_map(static fn (OutgoingMessage $due): string => $due->id, $this->repository->due($at, 10)));
        self::assertSame([], $this->repository->due($at->modify('-1 second'), 10));
        self::assertTrue($this->repository->claim($message, $at, $at->modify('+30 minutes')));
        self::assertFalse($this->repository->claim($message, $at->modify('+29 minutes'), $at->modify('+59 minutes')));
        self::assertSame([], $this->repository->due($at->modify('+29 minutes'), 10));

        $message->delivered($at);
        $this->entityManager->flush();
        self::assertSame(0, $this->repository->purgeOutgoing($at));
        self::assertSame(1, $this->repository->purgeOutgoing($at->modify('+1 second')));
    }

    public function testPricesLedgerParcelsAndReturnsAreReadBack(): void
    {
        $reseller = Partners::reseller();
        $this->repository->addPartner($reseller);
        $at = new DateTimeImmutable('2026-11-12 22:30:00');
        $cad = Currency::of('CAD');
        $this->repository->recordPrice(PublishedPrice::of($reseller, 'TEA-1', Money::of(1_000, $cad), Money::of(700, $cad), $at->modify('-10 days')));
        $this->repository->recordPrice(PublishedPrice::of($reseller, 'TEA-1', Money::of(1_000, $cad), Money::of(650, $cad), $at->modify('-2 days')));
        $this->repository->record(LedgerEntry::of($reseller, 'R-77', Money::of(1_400, $cad), $at, [['sku' => 'TEA-1', 'quantity' => 2, 'unit_cost' => 700]]));
        $this->repository->record(LedgerEntry::credit($reseller, 'R-77', 'RET-1', Money::of(-700, $cad), $at->modify('+1 day'), [['sku' => 'TEA-1', 'quantity' => 1, 'unit_cost' => 700]]));
        $this->repository->addShipment(PartnerShipment::of($reseller, 'SH-1', 'R-77', ['TEA-1' => 2], $at, 'Canada Post', 'CP1'));

        $return = PartnerReturn::request($reseller, 'RET-1', 'R-77', $cad, ['TEA-1' => 1], $at);
        $return->receive(['TEA-1' => 1], Money::of(700, $cad), $at->modify('+1 day'));

        $this->repository->addReturn($return);
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame([650], array_map(static fn (PublishedPrice $price): int => $price->cost->minorUnits, $this->repository->pricesInEffectSince('reseller', ['TEA-1'], 'CAD', $at->modify('-1 day'))['TEA-1'] ?? []));
        self::assertSame([700, 650], array_map(static fn (PublishedPrice $price): int => $price->cost->minorUnits, $this->repository->pricesInEffectSince('reseller', ['TEA-1'], 'CAD', $at->modify('-5 days'))['TEA-1'] ?? []));
        self::assertSame([1_400, [['sku' => 'TEA-1', 'quantity' => 2, 'unit_cost' => 700]]], [$this->repository->findEntry('reseller', 'R-77')?->amount->minorUnits, $this->repository->findEntry('reseller', 'R-77')?->lines]);
        self::assertSame([null, 'RET-1'], array_map(static fn (LedgerEntry $entry): ?string => $entry->returnNumber, $this->repository->entriesOf('reseller', 'R-77')));
        self::assertSame([['SH-1', ['TEA-1' => 2], '2026-11-13 03:30 UTC']], array_map(static fn (PartnerShipment $shipment): array => [$shipment->shipment, $shipment->lines, $shipment->shippedAt->format('Y-m-d H:i T')], $this->repository->shipmentsOf('reseller', 'R-77')));
        self::assertSame([['RET-1', 700, ['TEA-1' => 1]]], array_map(static fn (PartnerReturn $return): array => [$return->returnNumber, $return->credit?->minorUnits, $return->received()], $this->repository->returnsOf('reseller', 'R-77')));
        self::assertNull($this->repository->findReturn('reseller', 'RET-2'));
    }
}
