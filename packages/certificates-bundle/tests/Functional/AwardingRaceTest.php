<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CertificatesBundle\Application\Awarding;
use Phil\CertificatesBundle\Application\Contract\CertificateUrls;
use Phil\CertificatesBundle\Application\Courses;
use Phil\CertificatesBundle\Domain\Contract\CertificateRepository;
use Phil\CertificatesBundle\Domain\Exception\StatusIndexTaken;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\ValueObject\Attainment;
use Phil\CertificatesBundle\Domain\ValueObject\PassCriteria;
use Phil\CertificatesBundle\Infrastructure\Doctrine\DoctrineCertificateRepository;
use Phil\CertificatesBundle\Tests\Functional\App\KnownCompletions;
use Phil\CertificatesBundle\Tests\Functional\App\RacingCertificates;
use Phil\CertificatesBundle\Tests\Functional\App\TestKernel;
use Phil\OpenBadges\CredentialIssuer;
use Phil\OpenBadgesBundle\Application\Securing;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Two awards at once without a lock, on Doctrine and its registry: another award stores its certificate at the index
 * this one was given, and this one starts again with the next — or finds the certificate the other awarded on the same
 * completion.
 */
#[CoversClass(Awarding::class)]
#[CoversClass(DoctrineCertificateRepository::class)]
#[CoversClass(StatusIndexTaken::class)]
final class AwardingRaceTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $this->service(Courses::class)->define('first-aid', 'First aid', 'The gestures that save lives.', 'Score 70 % or more.', new PassCriteria(70), 12);
    }

    public function testTheAwardWhoseIndexWasTakenStartsAgainWithTheNext(): void
    {
        $certificates = $this->service(CertificateRepository::class);
        $racing = new RacingCertificates($certificates, $this->awarding($certificates), races: 1);

        $certificate = $this->awarding($racing)->award('first-aid', 'Ada Lovelace', 'ada@example.org', new Attainment(90));

        self::assertSame(1, $certificate->statusIndex);
        self::assertSame([0, 1], $racing->given);
        self::assertSame('Grace Hopper', $racing->rivals[0]->recipientName);
        self::assertSame(0, $racing->rivals[0]->statusIndex);
        self::assertSame(2, $certificates->nextStatusIndex());
        self::assertSame($certificate->id, $certificates->findByCode($certificate->code->value)?->id);
    }

    public function testTheAwardGivesUpAfterItsAttempts(): void
    {
        $certificates = $this->service(CertificateRepository::class);
        $racing = new RacingCertificates($certificates, $this->awarding($certificates), races: Awarding::ATTEMPTS);

        try {
            $this->awarding($racing)->award('first-aid', 'Ada Lovelace', 'ada@example.org', new Attainment(90));
            self::fail('Every index was taken.');
        } catch (StatusIndexTaken $statusIndexTaken) {
            self::assertSame('The status index 2 was taken by another certificate in the meantime.', $statusIndexTaken->getMessage());
        }

        self::assertSame([0, 1, 2], $racing->given);
        self::assertSame(3, $certificates->nextStatusIndex());
    }

    public function testAnAwardOnCompletionWhoseCompletionWasTakenGivesBackTheOtherCertificate(): void
    {
        $this->service(Courses::class)->changeCompletionSource('first-aid', 'first-aid-2027');
        $certificates = $this->service(CertificateRepository::class);
        $onCompletion = static fn (Awarding $awarding): Certificate => $awarding->awardOnCompletion('first-aid', 'member-42', 'Ada Lovelace', 'ada@example.org', new Attainment(90));
        $racing = new RacingCertificates($certificates, $this->awarding($certificates), 1, $onCompletion);

        $certificate = $onCompletion($this->awarding($racing));

        self::assertSame([0], $racing->given);
        self::assertSame($racing->rivals[0]->id, $certificate->id);
        self::assertSame(['enrolment-7', 0], [$certificate->completion, $certificate->statusIndex]);
        self::assertSame(1, $certificates->nextStatusIndex());
    }

    private function awarding(CertificateRepository $certificates): Awarding
    {
        return new Awarding(
            $this->service(Courses::class),
            $certificates,
            $this->service(CredentialIssuer::class),
            $this->service(Securing::class),
            $this->service(CertificateUrls::class),
            $this->service(ClockInterface::class, 'clock'),
            completions: $this->service(KnownCompletions::class),
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $class, ?string $id = null): object
    {
        $service = self::getContainer()->get($id ?? $class);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
