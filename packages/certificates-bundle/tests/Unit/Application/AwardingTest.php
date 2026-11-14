<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\CertificatesBundle\Application\Awarding;
use Phil\CertificatesBundle\Application\Courses;
use Phil\CertificatesBundle\Application\StatusListLayout;
use Phil\CertificatesBundle\Domain\Contract\CertificateRepository;
use Phil\CertificatesBundle\Domain\Event\CertificateAwarded;
use Phil\CertificatesBundle\Domain\Exception\CertificateRefused;
use Phil\CertificatesBundle\Domain\Exception\StatusIndexTaken;
use Phil\CertificatesBundle\Domain\ValueObject\Attainment;
use Phil\CertificatesBundle\Domain\ValueObject\PassCriteria;
use Phil\CertificatesBundle\Tests\Unit\Support\FixedUrls;
use Phil\CertificatesBundle\Tests\Unit\Support\InMemoryCertificates;
use Phil\CertificatesBundle\Tests\Unit\Support\InMemoryCourses;
use Phil\CertificatesBundle\Tests\Unit\Support\Kit;
use Phil\CertificatesBundle\Tests\Unit\Support\LockWatchingCertificates;
use Phil\CertificatesBundle\Tests\Unit\Support\RecordedEvents;
use Phil\OpenBadges\CredentialIssuer;
use Phil\OpenBadges\Recipient;
use Phil\OpenBadgesBundle\Application\Securing;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Uid\Uuid;

#[CoversClass(Awarding::class)]
#[CoversClass(StatusIndexTaken::class)]
final class AwardingTest extends TestCase
{
    private InMemoryCourses $courses;

    private InMemoryCertificates $certificates;

    #[Override]
    protected function setUp(): void
    {
        $this->courses = new InMemoryCourses();
        $this->certificates = new InMemoryCertificates(5);
    }

    public function testACertificateCarriesASignedCredentialOfItsCourse(): void
    {
        $certificate = $this->awarding()->award('first-aid', 'Ada Lovelace', 'Ada@Example.org', new Attainment(70), listed: true);

        self::assertSame($certificate, $this->certificates->findByCode($certificate->code->value));
        self::assertSame(['Ada Lovelace', 5, true, '2026-11-08 10:00', '2027-11-08 10:00'], [$certificate->recipientName, $certificate->statusIndex, $certificate->listed, $certificate->awardedAt->format('Y-m-d H:i'), $certificate->validUntil?->format('Y-m-d H:i')]);
        self::assertTrue(Uuid::isValid($certificate->id));
        self::assertTrue($certificate->isToken());

        $verified = Kit::verifier()->verify($certificate->credential, new DateTimeImmutable('2026-11-09 UTC'));
        $credential = $verified->credential;
        $subject = $credential['credentialSubject'];
        self::assertIsArray($subject);
        self::assertSame('urn:uuid:'.$certificate->id, $credential['id']);
        self::assertSame('2026-11-08T10:00:00Z', $credential['validFrom']);
        self::assertSame('2027-11-08T10:00:00Z', $credential['validUntil']);
        self::assertSame(['id' => 'https://example.com/certificates/courses/first-aid', 'type' => ['Achievement'], 'name' => 'First aid', 'description' => 'The gestures that save lives.', 'criteria' => ['narrative' => 'Score 70 % or more on the final assessment.'], 'image' => ['id' => 'https://example.com/badges/first-aid.png', 'type' => 'Image']], $subject['achievement']);
        self::assertTrue(Recipient::identifies($subject, 'ada@example.org'));
        self::assertSame('https://example.com/open-badges/status/awards-2', $verified->status()?->statusListCredential);
        self::assertSame(1, $verified->status()->index);
        self::assertStringNotContainsString('ada@example.org', strtolower($certificate->credential));
    }

    public function testTheCredentialMayBeADocumentWithItsProofForAnyIdentity(): void
    {
        $clock = new MockClock(new DateTimeImmutable('2026-11-08 10:00:00 UTC'));
        $awarding = $this->awarding(new Securing(Kit::signer(), Kit::METHOD, Securing::DATA_INTEGRITY, $clock), null);

        $this->courses->courses['first-aid']->revise('Secourisme général', 'Les gestes.', 'Réussir.');
        $certificate = $awarding->award('first-aid', 'Ada', 'https://ada.example.org', new Attainment(100));
        self::assertStringContainsString('"name":"Secourisme général"', $certificate->credential);
        self::assertFalse($certificate->isToken());
        self::assertFalse($certificate->listed);
        self::assertNull($certificate->validUntil);
        self::assertStringNotContainsString('\/', $certificate->credential);
        $subject = Kit::verifier()->verify($certificate->credential)->credential['credentialSubject'];
        self::assertIsArray($subject);
        self::assertTrue(Recipient::identifies($subject, 'https://ada.example.org'));
        self::assertArrayNotHasKey('validUntil', Kit::verifier()->verify($certificate->credential)->credential);

        $did = $awarding->award('first-aid', 'Ada', 'did:example:ada', new Attainment(100));
        $subject = Kit::verifier()->verify($did->credential)->credential['credentialSubject'];
        self::assertIsArray($subject);
        self::assertSame('did:example:ada', $subject['id']);
        self::assertSame(6, $did->statusIndex);
    }

    public function testTheSaltOfTheIdentityIsDrawnForEachCertificate(): void
    {
        $awarding = $this->awarding();
        $salts = [];

        for ($i = 0; $i < 2; ++$i) {
            $subject = Kit::verifier()->verify($awarding->award('first-aid', 'Ada', 'ada@example.org', new Attainment(70))->credential)->credential['credentialSubject'];
            self::assertIsArray($subject);
            self::assertIsArray($subject['identifier']);
            self::assertIsArray($subject['identifier'][0]);
            self::assertIsString($subject['identifier'][0]['salt']);
            self::assertSame(16, \strlen($subject['identifier'][0]['salt']));
            $salts[] = $subject['identifier'][0]['salt'];
        }

        self::assertNotSame($salts[0], $salts[1]);
    }

    public function testTheIndexIsTakenAndTheCertificateStoredUnderTheLockThenTheEventDispatched(): void
    {
        $locks = new LockFactory(new InMemoryStore());
        $certificates = new LockWatchingCertificates($locks);
        $events = new RecordedEvents();

        $certificate = $this->awarding(certificates: $certificates, locks: $locks, events: $events)->award('first-aid', 'Ada', 'ada@example.org', new Attainment(70));

        self::assertSame(['next:held', 'save:held'], $certificates->held);
        self::assertFalse($certificates->isHeld());
        self::assertEquals([new CertificateAwarded($certificate->id, $certificate->code->value, 'first-aid')], $events->events);
        self::assertSame('phil_certificates.status_index', Awarding::LOCK);
    }

    public function testTheLockIsWaitedForThenReleasedWhateverHappens(): void
    {
        foreach ([false, true] as $refuse) {
            $lock = $this->createMock(SharedLockInterface::class);
            $lock->expects(self::once())->method('acquire')->with(true)->willReturn(true);
            $lock->expects(self::once())->method('release');

            $locks = $this->createMock(LockFactory::class);
            $locks->expects(self::once())->method('createLock')->with(Awarding::LOCK, 30.0)->willReturn($lock);

            $certificates = new LockWatchingCertificates(new LockFactory(new InMemoryStore()));
            $certificates->refuse = $refuse;

            try {
                $this->awarding(certificates: $certificates, locks: $locks)->award('first-aid', 'Ada', 'ada@example.org', new Attainment(70));
            } catch (RuntimeException) {
                self::assertTrue($refuse);
            }
        }
    }

    public function testTheLockIsReleasedAndNothingDispatchedWhenTheStorageRefuses(): void
    {
        $locks = new LockFactory(new InMemoryStore());
        $certificates = new LockWatchingCertificates($locks);
        $certificates->refuse = true;

        $events = new RecordedEvents();

        try {
            $this->awarding(certificates: $certificates, locks: $locks, events: $events)->award('first-aid', 'Ada', 'ada@example.org', new Attainment(70));
            self::fail('The storage refuses.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The storage refuses.', $runtimeException->getMessage());
        }

        self::assertFalse($certificates->isHeld());
        self::assertSame([], $events->events);
    }

    public function testWithoutALockTheIndexIsTakenAsTheStorageGivesIt(): void
    {
        $locks = new LockFactory(new InMemoryStore());
        $certificates = new LockWatchingCertificates($locks);

        $this->awarding(certificates: $certificates)->award('first-aid', 'Ada', 'ada@example.org', new Attainment(70));

        self::assertSame(['next:free', 'save:free'], $certificates->held);
    }

    public function testAnAwardWhoseIndexWasTakenStartsAgainUnderTheLockWithTheCourseReadAgain(): void
    {
        $locks = new LockFactory(new InMemoryStore());
        $certificates = new LockWatchingCertificates($locks);
        $certificates->taken = Awarding::ATTEMPTS - 1;

        $events = new RecordedEvents();
        $awarding = $this->awarding(certificates: $certificates, locks: $locks, events: $events);

        $certificate = $awarding->award('first-aid', 'Ada', 'ada@example.org', new Attainment(70));

        self::assertSame(3, Awarding::ATTEMPTS);
        self::assertSame(['next:held', 'save:held', 'next:held', 'save:held', 'next:held', 'save:held'], $certificates->held);
        self::assertFalse($certificates->isHeld());
        self::assertSame(['first-aid', 'first-aid', 'first-aid'], $this->courses->read);
        self::assertSame($certificate, $certificates->inner->findByCode($certificate->code->value));
        self::assertCount(1, $certificates->inner->certificates);
        self::assertEquals([new CertificateAwarded($certificate->id, $certificate->code->value, 'first-aid')], $events->events);
    }

    public function testAnAwardWhoseIndexIsTakenAtEachAttemptGivesUpAndDispatchesNothing(): void
    {
        $locks = new LockFactory(new InMemoryStore());
        $certificates = new LockWatchingCertificates($locks);
        $certificates->taken = Awarding::ATTEMPTS;

        $events = new RecordedEvents();

        try {
            $this->awarding(certificates: $certificates, locks: $locks, events: $events)->award('first-aid', 'Ada', 'ada@example.org', new Attainment(70));
            self::fail('Every attempt found its index taken.');
        } catch (StatusIndexTaken $statusIndexTaken) {
            self::assertSame('The status index 0 was taken by another certificate in the meantime.', $statusIndexTaken->getMessage());
            self::assertNull($statusIndexTaken->getPrevious());
            self::assertSame(0, $statusIndexTaken->getCode());
        }

        self::assertCount(2 * Awarding::ATTEMPTS, $certificates->held);
        self::assertSame(0, $certificates->taken);
        self::assertFalse($certificates->isHeld());
        self::assertSame([], $events->events);
    }

    public function testCriteriaNotMetAreRefused(): void
    {
        $this->expectException(CertificateRefused::class);
        $this->expectExceptionMessageIsOrContains('The criteria of the course "first-aid" are not met: score, module:cpr.');

        $this->awarding(criteria: new PassCriteria(70, requiredModules: ['cpr']))->award('first-aid', 'Ada', 'ada@example.org', new Attainment(69));
    }

    public function testARetiredCourseAwardsNothing(): void
    {
        $awarding = $this->awarding();
        $this->courses->courses['first-aid']->retire();

        try {
            $awarding->award('first-aid', 'Ada', 'ada@example.org', new Attainment(70));
            self::fail('The award is refused.');
        } catch (CertificateRefused $certificateRefused) {
            self::assertSame('The course "first-aid" is retired.', $certificateRefused->getMessage());
            self::assertSame(0, $this->certificates->saved);
        }
    }

    public function testAnUnknownCourseIsRefused(): void
    {
        $this->expectException(CertificateRefused::class);

        $this->awarding()->award('cpr', 'Ada', 'ada@example.org');
    }

    private function awarding(?Securing $securing = null, ?int $validityMonths = 12, PassCriteria $criteria = new PassCriteria(minimumScore: 70), ?CertificateRepository $certificates = null, ?LockFactory $locks = null, ?RecordedEvents $events = null): Awarding
    {
        $clock = new MockClock(new DateTimeImmutable('2026-11-08 10:00:00 UTC'));
        $this->courses = new InMemoryCourses();
        $this->courses->save(Kit::firstAid($validityMonths, $criteria));

        $this->certificates = new InMemoryCertificates(5);

        return new Awarding(
            new Courses($this->courses),
            $certificates ?? $this->certificates,
            new CredentialIssuer(Kit::issuer(), Kit::signer(), Kit::METHOD),
            $securing ?? new Securing(Kit::signer(), Kit::METHOD, Securing::JWT, $clock),
            new FixedUrls(),
            $clock,
            new StatusListLayout('awards', 2),
            $locks,
            $events,
        );
    }
}
