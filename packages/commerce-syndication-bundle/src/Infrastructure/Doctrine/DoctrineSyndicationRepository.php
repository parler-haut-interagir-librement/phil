<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Override;
use Phil\CommerceSyndicationBundle\Domain\Model\FedSku;
use Phil\CommerceSyndicationBundle\Domain\Model\LedgerEntry;
use Phil\CommerceSyndicationBundle\Domain\Model\OutgoingMessage;
use Phil\CommerceSyndicationBundle\Domain\Model\Partner;
use Phil\CommerceSyndicationBundle\Domain\Model\PartnerReturn;
use Phil\CommerceSyndicationBundle\Domain\Model\PartnerShipment;
use Phil\CommerceSyndicationBundle\Domain\Model\PublishedPrice;
use Phil\CommerceSyndicationBundle\Domain\Model\ReceivedMessage;
use Phil\CommerceSyndicationBundle\Domain\Model\SyndicatedProduct;
use Phil\CommerceSyndicationBundle\Domain\Partner\PartnerRole;
use Phil\CommerceSyndicationBundle\Domain\Repository\LedgerRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\MessageRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\PartnerRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\PartnerReturnRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\PartnerShipmentRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\PublishedPriceRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\SyndicatedProductRepository;
use SortDirection;

/**
 * The syndication's storage through Doctrine: partners, the outbox and the received ids, the syndicated products,
 * the ledger, the prices published to resellers, the shipments and returns between partners. Moments are stored and
 * compared as `datetime_utc_immutable`, the type of their columns: in UTC, whatever the zone of the process.
 */
final readonly class DoctrineSyndicationRepository implements PartnerRepository, MessageRepository, SyndicatedProductRepository, LedgerRepository, PublishedPriceRepository, PartnerShipmentRepository, PartnerReturnRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function findPartner(string $code): ?Partner
    {
        return $this->entityManager->find(Partner::class, $code);
    }

    #[Override]
    public function addPartner(Partner $partner): void
    {
        $this->entityManager->persist($partner);
    }

    #[Override]
    public function activeResellers(): array
    {
        return $this->entityManager->getRepository(Partner::class)->findBy(['role' => PartnerRole::Reseller, 'active' => true], ['code' => 'ASC']);
    }

    #[Override]
    public function resellersFed(string $sku): array
    {
        $fed = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Partner::class, 'p')
            ->where('p.role = :role')
            ->andWhere('p.active = true')
            ->andWhere(\sprintf('EXISTS (SELECT f.sku FROM %s f WHERE f.partner = p.code AND f.sku = :sku)', FedSku::class))
            ->orderBy('p.code', SortDirection::Ascending)
            ->setParameter('role', PartnerRole::Reseller)
            ->setParameter('sku', $sku);

        return $this->results($fed, Partner::class);
    }

    #[Override]
    public function queue(OutgoingMessage $message): void
    {
        $this->entityManager->persist($message);
    }

    #[Override]
    public function due(DateTimeImmutable $at, int $limit): array
    {
        /** @var list<OutgoingMessage> */
        return $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(OutgoingMessage::class, 'm')
            ->where('m.deliveredAt IS NULL')
            ->andWhere('m.nextAttemptAt <= :at')
            ->andWhere('m.claimedUntil IS NULL OR m.claimedUntil <= :at')
            ->orderBy('m.createdAt', SortDirection::Ascending)
            ->setParameter('at', $at, Types::DATETIME_UTC_IMMUTABLE)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    #[Override]
    public function claim(OutgoingMessage $message, DateTimeImmutable $at, DateTimeImmutable $until): bool
    {
        // One UPDATE, executed at once on the connection: of two runs, only one sees its row change.
        $outgoing = $this->entityManager->getClassMetadata(OutgoingMessage::class);
        $claimedUntil = $outgoing->getColumnName('claimedUntil');
        $claimed = $this->entityManager->getConnection()->executeStatement(
            \sprintf('UPDATE %1$s SET %2$s = ? WHERE %3$s = ? AND (%2$s IS NULL OR %2$s <= ?)', $outgoing->getTableName(), $claimedUntil, $outgoing->getColumnName('id')),
            [$until, $message->id, $at],
            [Types::DATETIME_UTC_IMMUTABLE, Types::STRING, Types::DATETIME_UTC_IMMUTABLE],
        );

        if (1 !== $claimed) {
            return false;
        }

        $message->claim($until);

        return true;
    }

    #[Override]
    public function wasReceived(string $partner, string $id): bool
    {
        return null !== $this->entityManager->find(ReceivedMessage::class, ['partner' => $partner, 'id' => $id]);
    }

    #[Override]
    public function markReceived(ReceivedMessage $message): void
    {
        $this->entityManager->persist($message);
    }

    #[Override]
    public function purgeOutgoing(DateTimeImmutable $before): int
    {
        $outgoing = $this->entityManager->getClassMetadata(OutgoingMessage::class);

        return (int) $this->entityManager->getConnection()->executeStatement(
            \sprintf('DELETE FROM %s WHERE %s < ? OR %s < ?', $outgoing->getTableName(), $outgoing->getColumnName('deliveredAt'), $outgoing->getColumnName('givenUpAt')),
            [$before, $before],
            [Types::DATETIME_UTC_IMMUTABLE, Types::DATETIME_UTC_IMMUTABLE],
        );
    }

    #[Override]
    public function purgeReceived(DateTimeImmutable $before): int
    {
        $received = $this->entityManager->getClassMetadata(ReceivedMessage::class);

        return (int) $this->entityManager->getConnection()->executeStatement(
            \sprintf('DELETE FROM %s WHERE %s < ?', $received->getTableName(), $received->getColumnName('receivedAt')),
            [$before],
            [Types::DATETIME_UTC_IMMUTABLE],
        );
    }

    #[Override]
    public function recordPrice(PublishedPrice $price): void
    {
        $this->entityManager->persist($price);
    }

    #[Override]
    public function latestPrice(string $reseller, string $sku, string $currency): ?PublishedPrice
    {
        return $this->entityManager->getRepository(PublishedPrice::class)->findOneBy(['reseller' => $reseller, 'sku' => $sku, 'currencyCode' => $currency], ['publishedAt' => 'DESC', 'id' => 'DESC']);
    }

    #[Override]
    public function latestPrices(string $sku, string $currency, array $resellers): array
    {
        $latest = $this->prices($currency)
            ->andWhere('p.sku = :sku')
            ->andWhere('p.reseller IN (:resellers)')
            ->andWhere($this->noLaterPrice(''))
            ->setParameter('sku', $sku)
            ->setParameter('resellers', $resellers);
        $prices = [];

        foreach ($this->results($latest, PublishedPrice::class) as $price) {
            $prices[$price->reseller] = $price;
        }

        return $prices;
    }

    #[Override]
    public function pricesInEffectSince(string $reseller, array $skus, string $currency, DateTimeImmutable $since): array
    {
        // The last price of each SKU before the window, then every price within it: two queries, whatever the number
        // of SKUs.
        $before = $this->prices($currency)
            ->andWhere('p.reseller = :reseller')
            ->andWhere('p.sku IN (:skus)')
            ->andWhere('p.publishedAt < :since')
            ->andWhere($this->noLaterPrice(' AND q.publishedAt < :since'))
            ->setParameter('reseller', $reseller)
            ->setParameter('skus', $skus)
            ->setParameter('since', $since, Types::DATETIME_UTC_IMMUTABLE);

        $recent = $this->prices($currency)
            ->andWhere('p.reseller = :reseller')
            ->andWhere('p.sku IN (:skus)')
            ->andWhere('p.publishedAt >= :since')
            ->setParameter('reseller', $reseller)
            ->setParameter('skus', $skus)
            ->setParameter('since', $since, Types::DATETIME_UTC_IMMUTABLE)
            ->orderBy('p.publishedAt', SortDirection::Ascending)
            ->addOrderBy('p.id', SortDirection::Ascending);

        $prices = [];

        foreach ([...$this->results($before, PublishedPrice::class), ...$this->results($recent, PublishedPrice::class)] as $price) {
            $prices[$price->sku][] = $price;
        }

        return $prices;
    }

    #[Override]
    public function findProduct(string $supplier, string $sku): ?SyndicatedProduct
    {
        return $this->entityManager->find(SyndicatedProduct::class, ['supplier' => $supplier, 'sku' => $sku]);
    }

    #[Override]
    public function addProduct(SyndicatedProduct $product): void
    {
        $this->entityManager->persist($product);
    }

    #[Override]
    public function findEntry(string $partner, string $orderNumber): ?LedgerEntry
    {
        return $this->entityManager->getRepository(LedgerEntry::class)->findOneBy(['partner' => $partner, 'orderNumber' => $orderNumber, 'returnKey' => '']);
    }

    #[Override]
    public function entriesOf(string $partner, string $orderNumber): array
    {
        return $this->entityManager->getRepository(LedgerEntry::class)->findBy(['partner' => $partner, 'orderNumber' => $orderNumber], ['recordedAt' => 'ASC', 'id' => 'ASC']);
    }

    #[Override]
    public function record(LedgerEntry $entry): void
    {
        $this->entityManager->persist($entry);
    }

    #[Override]
    public function openEntries(string $partner): array
    {
        return $this->entityManager->getRepository(LedgerEntry::class)->findBy(['partner' => $partner, 'settledAt' => null], ['recordedAt' => 'ASC']);
    }

    #[Override]
    public function findShipment(string $partner, string $shipment): ?PartnerShipment
    {
        return $this->entityManager->find(PartnerShipment::class, ['partner' => $partner, 'shipment' => $shipment]);
    }

    #[Override]
    public function addShipment(PartnerShipment $shipment): void
    {
        $this->entityManager->persist($shipment);
    }

    #[Override]
    public function shipmentsOf(string $partner, string $orderNumber): array
    {
        return $this->entityManager->getRepository(PartnerShipment::class)->findBy(['partner' => $partner, 'orderNumber' => $orderNumber], ['shippedAt' => 'ASC', 'shipment' => 'ASC']);
    }

    #[Override]
    public function findReturn(string $partner, string $returnNumber): ?PartnerReturn
    {
        return $this->entityManager->find(PartnerReturn::class, ['partner' => $partner, 'returnNumber' => $returnNumber]);
    }

    #[Override]
    public function addReturn(PartnerReturn $return): void
    {
        $this->entityManager->persist($return);
    }

    #[Override]
    public function returnsOf(string $partner, string $orderNumber): array
    {
        return $this->entityManager->getRepository(PartnerReturn::class)->findBy(['partner' => $partner, 'orderNumber' => $orderNumber], ['requestedAt' => 'ASC', 'returnNumber' => 'ASC']);
    }

    private function prices(string $currency): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(PublishedPrice::class, 'p')
            ->where('p.currencyCode = :currency')
            ->setParameter('currency', $currency);
    }

    /**
     * No price of the same reseller, SKU and currency was published after `p` — later, or at the same moment with a
     * greater id, as `latestPrice()` orders them —, among those `$among` keeps.
     */
    private function noLaterPrice(string $among): string
    {
        return \sprintf(
            'NOT EXISTS (SELECT q.id FROM %s q WHERE q.reseller = p.reseller AND q.sku = p.sku AND q.currencyCode = p.currencyCode%s AND (q.publishedAt > p.publishedAt OR (q.publishedAt = p.publishedAt AND q.id > p.id)))',
            PublishedPrice::class,
            $among,
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    private function results(QueryBuilder $query, string $class): array
    {
        $entities = [];

        $result = $query->getQuery()->getResult();

        foreach (\is_array($result) ? $result : [] as $entity) {
            if ($entity instanceof $class) {
                $entities[] = $entity;
            }
        }

        return $entities;
    }
}
