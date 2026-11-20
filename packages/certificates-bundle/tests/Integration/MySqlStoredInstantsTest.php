<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\Model\Course;
use Phil\CertificatesBundle\Domain\Model\LearningActivity;
use Phil\CertificatesBundle\Infrastructure\Doctrine\DoctrineCertificateRepository;
use Phil\CertificatesBundle\Infrastructure\Doctrine\DoctrineLearningActivityRepository;
use Phil\CertificatesBundle\Tests\Unit\Infrastructure\StoredInstantsTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * The instants of the register on MySQL 8: `phil-dev services test` gives it `PHIL_TEST_MYSQL_URL`.
 */
#[CoversClass(Certificate::class)]
#[CoversClass(Course::class)]
#[CoversClass(LearningActivity::class)]
#[CoversClass(DoctrineCertificateRepository::class)]
#[CoversClass(DoctrineLearningActivityRepository::class)]
#[Group('mysql')]
final class MySqlStoredInstantsTest extends StoredInstantsTestCase
{
    #[Override]
    protected function entityManager(): EntityManagerInterface
    {
        $url = getenv('PHIL_TEST_MYSQL_URL');

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped('Start the servers with `phil-dev services up` and run `phil-dev services test` to run this test.');
        }

        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 2).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $entityManager = new EntityManager(DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql'])->parse($url)), $configuration);
        $schema = new SchemaTool($entityManager);
        $classes = $entityManager->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($classes);
        $schema->createSchema($classes);

        return $entityManager;
    }
}
