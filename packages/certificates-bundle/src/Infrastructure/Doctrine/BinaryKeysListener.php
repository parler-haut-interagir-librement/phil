<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\Model\Course;
use Phil\CertificatesBundle\Domain\Model\LearningActivity;

/**
 * Makes the keys of the register compare byte for byte on MySQL and MariaDB, whose default collations fold case and
 * accents: the code, the holder and the completion of a certificate, the slug and the completion source of a course,
 * and the member and the source of a learning activity take `utf8mb4_bin`, so that the members `Alice` and `alice`
 * never read each other's certificates, and two completions differing by their case are two completions. The other
 * platforms compare strings exactly already, and their mapping is left as it is. The identifiers, which foreign keys
 * reference, are left as they are on every platform.
 */
final class BinaryKeysListener
{
    /** The fields compared byte for byte, by entity. */
    private const array FIELDS = [
        Certificate::class => ['codeValue', 'holder', 'completion'],
        Course::class => ['slug', 'completionSource'],
        LearningActivity::class => ['member', 'source'],
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
            $mapping->options = ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin'] + ($mapping->options ?? []);
        }
    }
}
