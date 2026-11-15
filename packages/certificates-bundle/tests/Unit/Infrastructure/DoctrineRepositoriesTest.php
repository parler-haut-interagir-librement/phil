<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Override;
use Phil\CertificatesBundle\Domain\Exception\StatusIndexTaken;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\Model\Course;
use Phil\CertificatesBundle\Domain\Model\LearningActivity;
use Phil\CertificatesBundle\Domain\ValueObject\Credits;
use Phil\CertificatesBundle\Domain\ValueObject\MemberId;
use Phil\CertificatesBundle\Domain\ValueObject\PassCriteria;
use Phil\CertificatesBundle\Domain\ValueObject\Period;
use Phil\CertificatesBundle\Domain\ValueObject\VerificationCode;
use Phil\CertificatesBundle\Infrastructure\Doctrine\DoctrineCertificateRepository;
use Phil\CertificatesBundle\Infrastructure\Doctrine\DoctrineCourseRepository;
use Phil\CertificatesBundle\Infrastructure\Doctrine\DoctrineLearningActivityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoctrineCourseRepository::class)]
#[CoversClass(DoctrineCertificateRepository::class)]
#[CoversClass(DoctrineLearningActivityRepository::class)]
/**
 * The repositories on an entity manager of their own, over an SQLite database in memory.
 */
final class DoctrineRepositoriesTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    private DoctrineCourseRepository $courses;

    private DoctrineCertificateRepository $certificates;

    private DoctrineLearningActivityRepository $activities;

    #[Override]
    protected function setUp(): void
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $configuration);
        $this->entityManager = $entityManager;
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        $this->courses = new DoctrineCourseRepository($entityManager);
        $this->certificates = new DoctrineCertificateRepository($entityManager);
        $this->activities = new DoctrineLearningActivityRepository($entityManager);
    }

    public function testCoursesAreStoredAndTheOpenOnesListedByName(): void
    {
        $zoology = Course::define('zoology', 'Zoology', '', 'Pass.', new PassCriteria(60, 90, ['a', 'b']), 6, 'https://example.com/z.png');
        $botany = Course::define('botany', 'Botany', '', 'Pass.');
        $retired = Course::define('alchemy', 'Alchemy', '', 'Pass.');
        $retired->retire();

        foreach ([$zoology, $botany, $retired] as $course) {
            $this->courses->save($course);
        }

        $this->courses->save(Course::define('a-botany', 'Botany', '', 'Pass.'));
        $this->courses->save(Course::define('a-zz', 'Zz', '', 'Pass.'));

        $this->entityManager->clear();

        self::assertSame(['a-botany', 'botany', 'zoology', 'a-zz'], array_map(static fn (Course $course): string => $course->slug, $this->courses->open()));
        $found = $this->courses->findBySlug('zoology');
        self::assertInstanceOf(Course::class, $found);
        self::assertEquals(new PassCriteria(60, 90, ['a', 'b']), $found->criteria());
        self::assertSame([6, 'https://example.com/z.png', false], [$found->validityMonths, $found->image, $found->retired]);
        self::assertTrue($this->courses->findBySlug('alchemy')?->retired);
        self::assertNull($this->courses->findBySlug('cpr'));
    }

    public function testCertificatesAreFoundByCodeAndTheirIndexesAndListsRead(): void
    {
        $course = Course::define('botany', 'Botany', '', 'Pass.', validityMonths: 12);
        $other = Course::define('zoology', 'Zoology', '', 'Pass.');
        $this->courses->save($course);
        $this->courses->save($other);

        self::assertSame(0, $this->certificates->nextStatusIndex());

        $this->certificate($course, 'AAAAAAAAAAA1', 4, '2026-01-01');
        $second = $this->certificate($course, 'AAAAAAAAAAA2', 7, '2026-03-01');
        $third = $this->certificate($course, 'AAAAAAAAAAA3', 9, '2026-02-01');
        $this->certificate($course, 'AAAAAAAAAAA4', 10, '2026-04-01', false);
        $this->certificate($other, 'AAAAAAAAAAA5', 12, '2026-05-01');
        $second->revoke('Fraud.', new DateTimeImmutable('2026-06-01 UTC'));
        $third->revoke('Fraud.', new DateTimeImmutable('2026-06-01 UTC'));
        $this->certificates->save($third);
        $this->entityManager->clear();

        self::assertSame(13, $this->certificates->nextStatusIndex());
        self::assertSame([7, 9], $this->certificates->revokedIndexes(0, 12));
        self::assertSame([7], $this->certificates->revokedIndexes(7, 9));
        self::assertSame([9], $this->certificates->revokedIndexes(8, 10));
        self::assertSame([], $this->certificates->revokedIndexes(10, 20));

        $found = $this->certificates->findByCode('AAAAAAAAAAA2');
        self::assertInstanceOf(Certificate::class, $found);
        self::assertSame(['AAAAAAAAAAA2', 'Fraud.', 'botany', '2027-03-01'], [$found->code->value, $found->revocationReason, $found->course->slug, $found->validUntil?->format('Y-m-d')]);
        self::assertNull($this->certificates->findByCode('AAAAAAAAAAA9'));

        $course = $this->courses->findBySlug('botany');
        self::assertInstanceOf(Course::class, $course);
        self::assertSame(3, $this->certificates->countListed($course));
        self::assertSame(['AAAAAAAAAAA2', 'AAAAAAAAAAA3', 'AAAAAAAAAAA1'], array_map(static fn (Certificate $certificate): string => $certificate->code->value, $this->certificates->listed($course, 10)));
        self::assertSame(['AAAAAAAAAAA3'], array_map(static fn (Certificate $certificate): string => $certificate->code->value, $this->certificates->listed($course, 1, 1)));
    }

    public function testCoursesKeepTheirEndAndTheCreditsOfTheirRecertification(): void
    {
        $this->courses->save(Course::define('botany', 'Botany', '', 'Pass.', recertificationCredits: Credits::fromString('7.25'), endsAt: new DateTimeImmutable('2030-01-01 UTC')));
        $this->entityManager->clear();

        $found = $this->courses->findBySlug('botany');
        self::assertSame(725, $found?->recertificationCredits?->hundredths);
        self::assertSame('2030-01-01', $found->endsAt?->format('Y-m-d'));
    }

    public function testRenewalsAndTheCertificatesAboutToEndAreFound(): void
    {
        $course = Course::define('botany', 'Botany', '', 'Pass.', validityMonths: 12);
        $this->courses->save($course);
        $soon = $this->certificate($course, 'AAAAAAAAAAA1', 1, '2026-02-01');
        $this->certificate($course, 'AAAAAAAAAAA2', 2, '2026-01-15', id: 'id-z');
        $this->certificate($course, 'AAAAAAAAAAA3', 3, '2026-01-15', id: 'id-a');
        $revoked = $this->certificate($course, 'AAAAAAAAAAA4', 4, '2026-01-20');
        $revoked->revoke('Fraud.', new DateTimeImmutable('2026-06-01 UTC'));

        $this->certificates->save($revoked);
        $renewed = $this->certificate($course, 'AAAAAAAAAAA5', 5, '2026-01-25');
        $renewal = Certificate::award('id-renewal', VerificationCode::fromString('AAAAAAAAAAA6'), $course, 'Ada', new DateTimeImmutable('2027-01-01 UTC'), 6, 'a.b.c', holder: MemberId::fromString('member-1'), renews: $renewed);
        $this->certificates->save($renewal);
        $this->certificate($course, 'AAAAAAAAAAA7', 7, '2026-01-01');
        $this->certificate($course, 'AAAAAAAAAAA8', 8, '2026-03-01');
        $soon->remind(new DateTimeImmutable('2026-12-01 UTC'));
        $this->certificates->save($soon);
        $this->entityManager->clear();

        $codes = static fn (array $certificates): array => array_map(static fn (Certificate $certificate): string => $certificate->code->value, $certificates);
        $after = new DateTimeImmutable('2027-01-01 00:00:00 UTC');
        $until = new DateTimeImmutable('2027-02-01 00:00:00 UTC');

        self::assertSame(['AAAAAAAAAAA3', 'AAAAAAAAAAA2', 'AAAAAAAAAAA1'], $codes($this->certificates->expiring($after, $until, 10)));
        self::assertSame(['AAAAAAAAAAA2'], $codes($this->certificates->expiring($after, $until, 1, 1)));
        self::assertSame('2026-12-01', $this->certificates->findByCode('AAAAAAAAAAA1')?->remindedAt?->format('Y-m-d'));

        $renewedFound = $this->certificates->findByCode('AAAAAAAAAAA5');
        self::assertInstanceOf(Certificate::class, $renewedFound);
        $renewalFound = $this->certificates->findRenewalOf($renewedFound);
        self::assertInstanceOf(Certificate::class, $renewalFound);
        self::assertSame(['AAAAAAAAAAA6', 'member-1', 'AAAAAAAAAAA5'], [$renewalFound->code->value, $renewalFound->holder, $renewalFound->renews?->code->value]);
        self::assertNull($this->certificates->findRenewalOf($renewalFound));
    }

    public function testActivitiesAreStoredFoundListedAndSummed(): void
    {
        $botany = Course::define('botany', 'Botany', '', 'Pass.');
        $zoology = Course::define('zoology', 'Zoology', '', 'Pass.');
        $this->courses->save($botany);
        $this->courses->save($zoology);

        $member = MemberId::fromString('member-1');
        $year = new Period(new DateTimeImmutable('2026-01-01 UTC'), new DateTimeImmutable('2027-01-01 UTC'));

        $general = LearningActivity::record($member, 'General', Credits::fromString('1.25'), new DateTimeImmutable('2026-01-01 00:00:00 UTC'), source: 'event-1');
        $this->activities->save($general);
        $this->activities->save(LearningActivity::record($member, 'Botany', Credits::fromString('2'), new DateTimeImmutable('2026-06-01 UTC'), $botany));
        $this->activities->save(LearningActivity::record($member, 'Zoology', Credits::fromString('4'), new DateTimeImmutable('2026-12-31 23:59:59 UTC'), $zoology));
        $this->activities->save(LearningActivity::record($member, 'Next year', Credits::fromString('8'), new DateTimeImmutable('2027-01-01 00:00:00 UTC')));
        $this->activities->save(LearningActivity::record(MemberId::fromString('member-2'), 'Another', Credits::fromString('16'), new DateTimeImmutable('2026-06-01 UTC'), source: 'event-1'));

        $this->entityManager->clear();

        self::assertSame('7.25', $this->activities->total($member, $year)->formatted());
        self::assertSame('3.25', $this->activities->total($member, $year, $this->courses->findBySlug('botany'))->formatted());
        self::assertSame('0', $this->activities->total(MemberId::fromString('member-3'), $year)->formatted());
        self::assertSame(['Zoology', 'Botany', 'General'], array_map(static fn (LearningActivity $activity): string => $activity->title, $this->activities->of($member, $year, 10)));
        self::assertSame(['Botany'], array_map(static fn (LearningActivity $activity): string => $activity->title, $this->activities->of($member, $year, 1, 1)));

        $found = $this->activities->findBySource($member, 'event-1');
        self::assertInstanceOf(LearningActivity::class, $found);
        self::assertSame(['General', 125, 'member-1'], [$found->title, $found->credits->hundredths, $found->member]);
        self::assertNull($this->activities->findBySource($member, 'event-2'));
        self::assertSame('Another', $this->activities->findBySource(MemberId::fromString('member-2'), 'event-1')?->title);
        self::assertSame('Botany', $this->activities->of($member, $year, 1, 1)[0]->course?->name);

        $this->activities->remove($found);
        self::assertNull($this->activities->find($general->id));
        self::assertSame('6', $this->activities->total($member, $year)->formatted());
    }

    public function testTheSameSourceIsStoredOnceForAMember(): void
    {
        $member = MemberId::fromString('member-1');
        $this->activities->save(LearningActivity::record($member, 'A', Credits::fromString('1'), new DateTimeImmutable('2026-01-01 UTC'), source: 'event-1'));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->activities->save(LearningActivity::record($member, 'B', Credits::fromString('1'), new DateTimeImmutable('2026-01-01 UTC'), source: 'event-1'));
    }

    public function testCertificatesAwardedAtTheSameMomentAreListedInTheOrderOfTheirIdentifiers(): void
    {
        $course = Course::define('botany', 'Botany', '', 'Pass.');
        $this->courses->save($course);
        $this->certificate($course, 'AAAAAAAAAAA1', 1, '2026-01-01', id: '00000000-0000-7000-8000-000000000001');
        $this->certificate($course, 'AAAAAAAAAAA2', 2, '2026-01-01', id: '00000000-0000-7000-8000-000000000002');

        self::assertSame(['AAAAAAAAAAA2', 'AAAAAAAAAAA1'], array_map(static fn (Certificate $certificate): string => $certificate->code->value, $this->certificates->listed($course, 10)));
    }

    public function testATakenIndexResetsTheManagerOfTheRegistryAndAsksForAnotherAttempt(): void
    {
        $course = Course::define('botany', 'Botany', '', 'Pass.');
        $this->courses->save($course);
        $this->certificate($course, 'AAAAAAAAAAA1', 3, '2026-01-01');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->once())->method('resetManager')->with('certificates');
        $certificates = new DoctrineCertificateRepository($this->entityManager, $registry, 'certificates');

        try {
            $certificates->save(Certificate::award('id-2', VerificationCode::fromString('AAAAAAAAAAA2'), $course, 'Ada', new DateTimeImmutable('2026-01-02 UTC'), 3, 'a.b.c'));
            self::fail('The index 3 is taken.');
        } catch (StatusIndexTaken $statusIndexTaken) {
            self::assertSame('The status index 3 was taken by another certificate in the meantime.', $statusIndexTaken->getMessage());
            self::assertInstanceOf(UniqueConstraintViolationException::class, $statusIndexTaken->getPrevious());
        }
    }

    public function testCoursesKeepTheirCompletionSourceAndCertificatesTheirCompletion(): void
    {
        $course = Course::define('botany', 'Botany', '', 'Pass.', completionSource: 'botany-2027');
        $other = Course::define('zoology', 'Zoology', '', 'Pass.');
        $this->courses->save($course);
        $this->courses->save($other);

        $this->certificates->save(Certificate::award('id-1', VerificationCode::fromString('AAAAAAAAAAA1'), $course, 'Ada', new DateTimeImmutable('2026-01-02 UTC'), 1, 'a.b.c', completion: 'enrolment-7'));
        $this->certificates->save(Certificate::award('id-2', VerificationCode::fromString('AAAAAAAAAAA2'), $other, 'Ada', new DateTimeImmutable('2026-01-02 UTC'), 2, 'a.b.c', completion: 'enrolment-8'));

        $this->entityManager->clear();

        $course = $this->courses->findBySlug('botany');
        self::assertInstanceOf(Course::class, $course);
        self::assertSame('botany-2027', $course->completionSource);
        self::assertSame('id-1', $this->certificates->findByCompletion($course, 'enrolment-7')?->id);
        self::assertSame('enrolment-7', $this->certificates->findByCode('AAAAAAAAAAA1')?->completion);
        self::assertNull($this->certificates->findByCompletion($course, 'enrolment-8'));
        self::assertNull($this->certificates->findByCompletion($course, 'enrolment-9'));
    }

    public function testACompletionAwardedTwiceIsRefusedAsATakenIndex(): void
    {
        $course = Course::define('botany', 'Botany', '', 'Pass.', completionSource: 'botany-2027');
        $this->courses->save($course);
        $this->certificates->save(Certificate::award('id-1', VerificationCode::fromString('AAAAAAAAAAA1'), $course, 'Ada', new DateTimeImmutable('2026-01-02 UTC'), 1, 'a.b.c', completion: 'enrolment-7'));

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->once())->method('resetManager')->with(null);
        $certificates = new DoctrineCertificateRepository($this->entityManager, $registry);

        $this->expectException(StatusIndexTaken::class);
        $certificates->save(Certificate::award('id-2', VerificationCode::fromString('AAAAAAAAAAA2'), $course, 'Ada', new DateTimeImmutable('2026-01-02 UTC'), 2, 'a.b.c', completion: 'enrolment-7'));
    }

    public function testATakenIndexIsDoctrinesRefusalWithoutARegistry(): void
    {
        $course = Course::define('botany', 'Botany', '', 'Pass.');
        $this->courses->save($course);
        $this->certificate($course, 'AAAAAAAAAAA1', 3, '2026-01-01');

        $this->expectException(UniqueConstraintViolationException::class);
        $this->certificate($course, 'AAAAAAAAAAA2', 3, '2026-01-02');
    }

    private function certificate(Course $course, string $code, int $index, string $awardedAt, bool $listed = true, ?string $id = null): Certificate
    {
        $certificate = Certificate::award($id ?? 'id-'.$code, VerificationCode::fromString($code), $course, 'Ada', new DateTimeImmutable($awardedAt.' UTC'), $index, 'a.b.c', $listed);
        $this->certificates->save($certificate);

        return $certificate;
    }
}
