<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\Model\Course;
use Phil\CertificatesBundle\Domain\Model\LearningActivity;
use Phil\CertificatesBundle\Domain\ValueObject\Credits;
use Phil\CertificatesBundle\Domain\ValueObject\MemberId;
use Phil\CertificatesBundle\Domain\ValueObject\VerificationCode;
use Phil\CertificatesBundle\Infrastructure\Doctrine\DoctrineCertificateRepository;
use Phil\CertificatesBundle\Infrastructure\Doctrine\DoctrineCourseRepository;
use Phil\CertificatesBundle\Infrastructure\Doctrine\DoctrineLearningActivityRepository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The instants of the register on SQLite in memory, and the values its columns hold: UTC.
 */
#[CoversClass(Certificate::class)]
#[CoversClass(Course::class)]
#[CoversClass(LearningActivity::class)]
#[CoversClass(DoctrineCertificateRepository::class)]
#[CoversClass(DoctrineLearningActivityRepository::class)]
final class StoredInstantsTest extends StoredInstantsTestCase
{
    public function testTheInstantsAreKeptInUtc(): void
    {
        date_default_timezone_set('America/Toronto');
        $course = Course::define('botany', 'Botany', '', 'Pass.', validityMonths: 1, endsAt: new DateTimeImmutable('2027-01-01 00:00:00'));
        new DoctrineCourseRepository($this->entityManager)->save($course);
        $certificate = Certificate::award('id-1', VerificationCode::fromString('AAAAAAAAAAA1'), $course, 'Ada', new DateTimeImmutable('2026-07-01 08:00:00'), 1, 'a.b.c');
        $certificate->remind(new DateTimeImmutable('2026-07-20 09:00:00'));
        $certificate->revoke('Fraud.', new DateTimeImmutable('2026-07-21 10:00:00'));
        new DoctrineCertificateRepository($this->entityManager)->save($certificate);
        new DoctrineLearningActivityRepository($this->entityManager)->save(LearningActivity::record(MemberId::fromString('member-1'), 'Workshop', Credits::fromString('1'), new DateTimeImmutable('2026-12-01 08:00:00')));

        $connection = $this->entityManager->getConnection();
        self::assertSame(['2026-07-01 12:00:00', '2026-08-01 12:00:00', '2026-07-20 13:00:00', '2026-07-21 14:00:00'], $connection->fetchNumeric('SELECT awarded_at, valid_until, reminded_at, revoked_at FROM phil_certificates_certificate'));
        self::assertSame('2027-01-01 05:00:00', $connection->fetchOne('SELECT ends_at FROM phil_certificates_course'));
        self::assertSame('2026-12-01 13:00:00', $connection->fetchOne('SELECT completed_at FROM phil_certificates_activity'));

        $this->entityManager->clear();
        self::assertSame('UTC', new DoctrineCertificateRepository($this->entityManager)->findByCode('AAAAAAAAAAA1')?->awardedAt->getTimezone()->getName());
    }

    #[Override]
    protected function entityManager(): EntityManagerInterface
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $configuration);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        return $entityManager;
    }
}
