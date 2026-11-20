<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Integration;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CommerceLogisticsBundle\Application\Purchasing;
use Phil\CommerceLogisticsBundle\Application\SerialNumbers;
use Phil\CommerceLogisticsBundle\Application\ShippingWaves;
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\CommerceLogisticsBundle\Domain\Document\ShippingDocumentKind;
use Phil\CommerceLogisticsBundle\Domain\Model\Convention;
use Phil\CommerceLogisticsBundle\Domain\Model\ConventionQuantity;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Domain\Model\Shipment;
use Phil\CommerceLogisticsBundle\Domain\Model\ShippingDocument;
use Phil\CommerceLogisticsBundle\Domain\Model\ShippingWave;
use Phil\CommerceLogisticsBundle\Infrastructure\Doctrine\DoctrineLogisticsRepository;
use PHPUnit\Framework\TestCase;

/**
 * The module's mapping and queries on a real server, read from the environment: the tables created from the mapping,
 * dates and JSON read back as they were given, each query's filters and order.
 */
abstract class ServerTestCase extends TestCase
{
    private EntityManager $entityManager;

    private DoctrineLogisticsRepository $repository;

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

        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 2).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $parameters = new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url);
        $this->entityManager = new EntityManager(DriverManager::getConnection($parameters), $configuration);
        $tool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        $this->repository = new DoctrineLogisticsRepository($this->entityManager);
    }

    #[Override]
    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $this->entityManager->getConnection()->close();
        }
    }

    public function testStockPurchasesAndSerialsAreReadBack(): void
    {
        $at = new DateTimeImmutable('2026-11-12 10:00:00');
        $this->repository->addLocation(Location::of('warehouse', 'Entrepôt', 10));
        $this->entityManager->flush();
        $stock = new Stock($this->repository, $this->repository, $this->repository);
        $purchasing = new Purchasing($this->repository, $this->repository, $stock);
        $purchasing->order('PO-1', 'Dice Works', 'warehouse', ['1234' => 4, 'DICE' => 2], $at, new DateTimeImmutable('2026-11-20'));

        $stock->allowBackorders('DICE');
        $this->entityManager->flush();
        $this->entityManager->clear();

        $purchasing->receive('PO-1', ['1234' => 2], $at);
        new SerialNumbers($this->repository, $this->repository)->register('1234', 'warehouse', ['SN-1', 'SN-2'], $at);
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame([2, 2], [$purchasing->onOrder('1234'), $purchasing->onOrder('DICE')]);
        self::assertSame('2026-11-20', $this->repository->findPurchaseOrder('PO-1')?->expectedOn()?->format('Y-m-d'));
        self::assertTrue($stock->allowsBackorders('DICE'));
        self::assertSame(2, $this->repository->serialsInStock('1234', 'warehouse'));
        self::assertSame(0, $this->repository->serialsInStock('1234', 'store'));
    }

    public function testConventionsDocumentsAndWavesAreFoundByTheirQueries(): void
    {
        $fall = Convention::of('fall-con', 'Fall Con', new DateTimeImmutable('2026-12-20'), new DateTimeImmutable('2026-12-21'));
        $this->repository->addConvention($fall);
        $this->repository->addConvention(Convention::of('spring-con', 'Spring Con', new DateTimeImmutable('2027-04-20'), new DateTimeImmutable('2027-04-20')));
        $this->repository->addQuantity(ConventionQuantity::of($fall, 'SHIRT-M', 20));
        $this->repository->addQuantity(ConventionQuantity::of($fall, 'SHIRT-L', 0));

        $shipment = Shipment::of('2026-0001', ['product:A' => 1], new DateTimeImmutable());
        $this->repository->addShipment($shipment);
        $this->repository->addDocument(ShippingDocument::pickingSlip('PS-1', '2026-0001', new DateTimeImmutable()));
        $this->repository->addDocument(ShippingDocument::deliveryNote('DN-1', $shipment, new DateTimeImmutable()));

        $waves = new ShippingWaves($this->repository);
        $waves->plan('later', 'Later', new DateTimeImmutable('2027-02-01'));
        $waves->plan('sooner', 'Sooner', new DateTimeImmutable('2027-01-01'));
        $waves->plan('gone', 'Gone', new DateTimeImmutable('2026-10-01'));
        $waves->release('gone', new DateTimeImmutable());

        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame(['fall-con'], array_map(static fn (Convention $convention): string => $convention->code, $this->repository->conventionsMeeting(new DateTimeImmutable('2026-12-21 23:00'), new DateTimeImmutable('2027-04-19'))));
        self::assertSame(['fall-con', 'spring-con'], array_map(static fn (Convention $convention): string => $convention->code, $this->repository->conventionsMeeting(new DateTimeImmutable('2026-12-01'), new DateTimeImmutable('2027-04-20'))));
        self::assertSame(['SHIRT-L', 'SHIRT-M'], $this->repository->conventionSkus(['SHIRT-M', 'SHIRT-L', 'DICE']));
        self::assertSame(['PS-1', null], [$this->repository->findDocument(ShippingDocumentKind::PickingSlip, '2026-0001', null)?->number, $this->repository->findDocument(ShippingDocumentKind::PickingSlip, '2026-0001', null)?->shipment()]);
        self::assertSame(['DN-1', $shipment->id], [$this->repository->findDocument(ShippingDocumentKind::DeliveryNote, '2026-0001', $shipment->id)?->number, $this->repository->findDocument(ShippingDocumentKind::DeliveryNote, '2026-0001', $shipment->id)?->shipment()]);
        self::assertSame([['product:A' => 1]], array_map(static fn (Shipment $shipped): array => $shipped->lines, $this->repository->shipmentsOf('2026-0001')));
        self::assertSame(['sooner', 'later'], array_map(static fn (ShippingWave $wave): string => $wave->code, $waves->planned()));
    }
}
