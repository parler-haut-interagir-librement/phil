<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignmentAgreement;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignorPayout;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignorStatement;
use Phil\CommerceConsignmentBundle\Domain\Model\LedgerEntry;
use Phil\CommerceConsignmentBundle\Domain\Model\StockLayer;

/**
 * Gives the keys of the ledger the binary collation of MySQL and MariaDB as the ORM loads their metadata, so that the
 * schema it generates, and the migration `doctrine:migrations:diff` writes, compare them byte by byte: the stock of the
 * SKU `bk-1` is never sold for `BK-1`, nor the consignor `ann` paid for `Ann`. PostgreSQL's default collations are
 * deterministic and SQLite compares bytes: their columns keep the default, and the mapping stays the same everywhere.
 */
final readonly class BinaryKeysListener
{
    /**
     * The fields that compare byte by byte, by entity.
     */
    public const array FIELDS = [
        StockLayer::class => ['id', 'origin', 'sku', 'location', 'consignor', 'reference'],
        LedgerEntry::class => ['id', 'layer', 'sku', 'location', 'consignor', 'reference'],
        ConsignmentAgreement::class => ['id', 'consignor'],
        ConsignorStatement::class => ['id', 'number', 'consignor'],
        ConsignorPayout::class => ['id', 'idempotencyKey', 'consignor', 'reference', 'reverses'],
    ];

    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        $metadata = $event->getClassMetadata();
        $fields = self::FIELDS[$metadata->getName()] ?? [];

        if ([] === $fields || !$event->getObjectManager()->getConnection()->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            return;
        }

        foreach ($fields as $field) {
            $mapping = $metadata->getFieldMapping($field);
            $mapping->options = [...$mapping->options ?? [], 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin'];
        }
    }
}
