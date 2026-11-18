<?php

declare(strict_types=1);

namespace Phil\PollBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Phil\PollBundle\Domain\Model\Participation;
use Phil\PollBundle\Domain\Model\Poll;
use Phil\PollBundle\Domain\Model\Vote;

/**
 * Gives the identifiers of the bundle's entities the binary collation of MySQL and MariaDB as the ORM loads their
 * metadata, so that the schema it generates, and the migration `doctrine:migrations:diff` writes, compare them byte by
 * byte: a ballot of `user:Ann` is never one of `user:ann`, and a poll about `post:Abc` is never listed for `post:abc`. PostgreSQL's default collations are deterministic and SQLite compares bytes: their columns keep
 * the default, and the mapping stays the same for every platform.
 */
final readonly class BinaryKeysListener
{
    /**
     * The fields that compare byte by byte, by entity.
     */
    public const array FIELDS = [
        Poll::class => ['id', 'subject'],
        Vote::class => ['id', 'poll', 'voter', 'receipt'],
        Participation::class => ['poll', 'voter'],
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
