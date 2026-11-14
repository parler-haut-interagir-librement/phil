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
use Phil\CertificatesBundle\Domain\Event\CertificateRenewed;
use Phil\CertificatesBundle\Domain\Exception\CertificateRefused;
use Phil\CertificatesBundle\Domain\Exception\InvalidValue;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\Model\Course;
use Phil\CertificatesBundle\Domain\Model\LearningActivity;
use Phil\CertificatesBundle\Domain\ValueObject\Attainment;
use Phil\CertificatesBundle\Domain\ValueObject\Credits;
use Phil\CertificatesBundle\Domain\ValueObject\MemberId;
use Phil\CertificatesBundle\Domain\ValueObject\PassCriteria;
use Phil\CertificatesBundle\Domain\ValueObject\VerificationCode;
use Phil\CertificatesBundle\Tests\Unit\Support\FixedUrls;
use Phil\CertificatesBundle\Tests\Unit\Support\InMemoryActivities;
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
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

#[CoversClass(Awarding::class)]
#[CoversClass(CertificateRenewed::class)]
final class RecertificationTest extends TestCase
{
    private InMemoryCourses $courses;

    private InMemoryCertificates $certificates;

    private InMemoryActivities $activities;

    private RecordedEvents $events;

    private MockClock $clock;

    #[Override]
    protected function setUp(): void
    {
        $this->courses = new InMemoryCourses();
        $this->certificates = new InMemoryCertificates();
        $this->activities = new InMemoryActivities();
        $this->events = new RecordedEvents();
        $this->clock = new MockClock(new DateTimeImmutable('2026-11-08 10:00:00 UTC'));
    }

    public function testTheHolderIsKeptOnTheCertificate(): void
    {
        $this->course();
        $certificate = $this->awarding()->award('first-aid', 'Ada', 'ada@example.org', new Attainment(80), holder: 'member-1');

        self::assertSame('member-1', $certificate->holder);
        self::assertNull($this->awarding()->award('first-aid', 'Ada', 'ada@example.org', new Attainment(80))->holder);

        $this->expectException(InvalidValue::class);
        $this->awarding()->award('first-aid', 'Ada', 'ada@example.org', new Attainment(80), holder: 'ada@example.org');
    }

    public function testACourseThatEndedAwardsNoMore(): void
    {
        $this->course(endsAt: new DateTimeImmutable('2026-11-08 10:00:00 UTC'));

        $this->expectException(CertificateRefused::class);
        $this->expectExceptionMessageIsOrContains('The course "first-aid" has ended.');

        $this->awarding()->award('first-aid', 'Ada', 'ada@example.org', new Attainment(80));
    }

    public function testACourseThatEndsLaterAwardsUntilItsEnd(): void
    {
        $this->course(endsAt: new DateTimeImmutable('2027-01-01 UTC'));

        self::assertSame('2027-01-01', $this->awarding()->award('first-aid', 'Ada', 'ada@example.org', new Attainment(80))->validUntil?->format('Y-m-d'));
    }

    public function testTheCreditsGatheredSinceTheAwardRenewTheCertificate(): void
    {
        $this->course(Credits::fromString('10'));
        $first = $this->awarding()->award('first-aid', 'Ada Lovelace', 'ada@example.org', new Attainment(80), true, 'member-1');
        $this->record('member-1', '6', '2027-01-15');
        $this->record('member-1', '4', '2027-09-01');
        $this->record('member-2', '40', '2027-09-01');
        $this->record('member-1', '40', '2026-11-08 09:59:59');
        $this->clock->modify('2027-10-01 12:00 UTC');
        $this->events->events = [];

        $renewal = $this->awarding()->recertify($first->code->formatted(), 'ada@example.org');

        self::assertSame($first, $renewal->renews);
        self::assertSame($renewal, $this->certificates->findRenewalOf($first));
        self::assertSame(['Ada Lovelace', 'member-1', true, '2027-10-01 12:00', '2028-11-08 10:00'], [$renewal->recipientName, $renewal->holder, $renewal->listed, $renewal->awardedAt->format('Y-m-d H:i'), $renewal->validUntil?->format('Y-m-d H:i')]);
        self::assertNotSame($first->code->value, $renewal->code->value);
        self::assertSame(1, $renewal->statusIndex);
        self::assertEquals([new CertificateAwarded($renewal->id, $renewal->code->value, 'first-aid'), new CertificateRenewed($renewal->id, $renewal->code->value, 'first-aid', $first->id)], $this->events->events);

        $verified = Kit::verifier()->verify($renewal->credential, new DateTimeImmutable('2028-01-01 UTC'));
        $subject = $verified->credential['credentialSubject'];
        self::assertIsArray($subject);
        self::assertTrue(Recipient::identifies($subject, 'ada@example.org'));
        self::assertSame('2028-11-08T10:00:00Z', $verified->credential['validUntil']);
        self::assertSame('2027-10-01T12:00:00Z', $verified->credential['validFrom']);
    }

    public function testTheCreditsOfAnotherCourseDoNotCount(): void
    {
        $this->course(Credits::fromString('10'));
        $this->courses->save(Course::define('cpr', 'CPR', '', 'Pass.'));
        $first = $this->awarding()->award('first-aid', 'Ada', 'ada@example.org', new Attainment(80), holder: 'member-1');
        $this->record('member-1', '9.99', '2027-01-15', 'first-aid');
        $this->record('member-1', '5', '2027-01-15', 'cpr');
        $this->clock->modify('2027-10-01 UTC');

        $this->expectException(CertificateRefused::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The holder of the certificate %s gathered 9.99 of the 10 credits the course "first-aid" asks for.', $first->code->formatted()));

        $this->awarding()->recertify($first->code->value, 'ada@example.org');
    }

    public function testTheCreditsOfTheLastMomentCount(): void
    {
        $this->course(Credits::fromString('1'));
        $first = $this->awarding()->award('first-aid', 'Ada', 'ada@example.org', new Attainment(80), holder: 'member-1');
        $this->clock->modify('2027-10-01 UTC');
        $this->record('member-1', '1', '2027-10-01 00:00:00');

        self::assertSame($first, $this->awarding()->recertify($first->code->value, 'ada@example.org')->renews);
    }

    public function testACourseWithoutCreditsIsRenewedByAnAttainment(): void
    {
        $this->course();
        $first = $this->awarding()->award('first-aid', 'Ada', 'did:example:ada', new Attainment(80));
        $this->clock->modify('2028-01-01 UTC');

        try {
            $this->awarding()->recertify($first->code->value, 'did:example:ada');
            self::fail('An attainment is asked for.');
        } catch (CertificateRefused $certificateRefused) {
            self::assertSame('The course "first-aid" asks for no credits: a recertification shows an attainment that meets its criteria.', $certificateRefused->getMessage());
        }

        try {
            $this->awarding()->recertify($first->code->value, 'did:example:ada', new Attainment(60));
            self::fail('The criteria are checked.');
        } catch (CertificateRefused $certificateRefused) {
            self::assertSame('The criteria of the course "first-aid" are not met: score.', $certificateRefused->getMessage());
        }

        $renewal = $this->awarding()->recertify($first->code->value, 'did:example:ada', new Attainment(70), listed: true);
        self::assertTrue($renewal->listed);
        self::assertFalse($first->listed);
        self::assertSame('2029-01-01', $renewal->validUntil?->format('Y-m-d'));
    }

    public function testCreditsAndAnAttainmentAreBothChecked(): void
    {
        $this->course(Credits::fromString('1'));
        $first = $this->awarding()->award('first-aid', 'Ada', 'ada@example.org', new Attainment(80), holder: 'member-1');
        $this->record('member-1', '1', '2027-01-01');

        $this->expectException(CertificateRefused::class);
        $this->expectExceptionMessageIsOrContains('The criteria of the course "first-aid" are not met: score.');

        $this->awarding()->recertify($first->code->value, 'ada@example.org', new Attainment(10));
    }

    public function testCreditsAskForAHolderAndTheirStorage(): void
    {
        $this->course(Credits::fromString('1'));
        $first = $this->awarding()->award('first-aid', 'Ada', 'ada@example.org', new Attainment(80));
        $message = \sprintf('The course "first-aid" asks for credits of continuing education, and the certificate %s names no holder they are counted for.', $first->code->formatted());

        try {
            $this->awarding()->recertify($first->code->value, 'ada@example.org');
            self::fail('A holder is asked for.');
        } catch (CertificateRefused $certificateRefused) {
            self::assertSame($message, $certificateRefused->getMessage());
        }

        $held = $this->awarding()->award('first-aid', 'Ada', 'ada@example.org', new Attainment(80), holder: 'member-1');
        $this->record('member-1', '5', '2027-01-01');

        $this->expectException(CertificateRefused::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The course "first-aid" asks for credits of continuing education, and the certificate %s names no holder they are counted for.', $held->code->formatted()));

        $this->awarding(false)->recertify($held->code->value, 'ada@example.org');
    }

    public function testTheIdentityMustBeTheRecipientsOfTheFirstCertificate(): void
    {
        $this->course();
        $token = $this->awarding()->award('first-aid', 'Ada', 'ada@example.org', new Attainment(80));
        $document = $this->awarding(securing: Securing::DATA_INTEGRITY)->award('first-aid', 'Ada', 'https://ada.example.org', new Attainment(80));

        foreach ([[$token, 'grace@example.org'], [$document, 'https://grace.example.org']] as [$certificate, $identity]) {
            try {
                $this->awarding()->recertify($certificate->code->value, $identity, new Attainment(90));
                self::fail('Another identity is refused.');
            } catch (CertificateRefused $certificateRefused) {
                self::assertSame(\sprintf('The identity given is not the recipient of the certificate %s.', $certificate->code->formatted()), $certificateRefused->getMessage());
            }
        }

        self::assertSame($document, $this->awarding()->recertify($document->code->value, 'https://ada.example.org', new Attainment(90))->renews);
    }

    public function testACredentialThatCannotBeReadIsNoRecipients(): void
    {
        $this->course();

        foreach (['not.a.token', '{"credentialSubject":', '"text"', '{"credentialSubject":"ada"}'] as $index => $credential) {
            $certificate = Certificate::award('0190f1d2-3c4b-7a8e-9f00-11223344550'.$index, VerificationCode::generate(), $this->courses->courses['first-aid'], 'Ada', new DateTimeImmutable('2026-11-08 UTC'), 10 + $index, $credential);
            $this->certificates->save($certificate);

            try {
                $this->awarding()->recertify($certificate->code->value, 'ada@example.org', new Attainment(90));
                self::fail('Refused.');
            } catch (CertificateRefused $certificateRefused) {
                self::assertStringStartsWith('The identity given is not the recipient', $certificateRefused->getMessage());
            }
        }
    }

    public function testARevokedRenewedOrUnknownCertificateIsNotRenewed(): void
    {
        $this->course();
        $revoked = $this->awarding()->award('first-aid', 'Ada', 'ada@example.org', new Attainment(80));
        $revoked->revoke('Fraud.', $this->clock->now());

        $renewed = $this->awarding()->award('first-aid', 'Ada', 'ada@example.org', new Attainment(80));
        $this->awarding()->recertify($renewed->code->value, 'ada@example.org', new Attainment(80));

        foreach ([
            [$revoked->code->value, \sprintf('The certificate %s is revoked: it is not renewed.', $revoked->code->formatted())],
            [$renewed->code->value, \sprintf('The certificate %s is already renewed.', $renewed->code->formatted())],
            ['0000-0000-0000', 'There is no certificate 0000-0000-0000.'],
        ] as [$code, $message]) {
            try {
                $this->awarding()->recertify($code, 'ada@example.org', new Attainment(80));
                self::fail($message);
            } catch (CertificateRefused $certificateRefused) {
                self::assertSame($message, $certificateRefused->getMessage());
            }
        }
    }

    public function testAnExpiredCertificateIsRenewedFromNow(): void
    {
        $this->course();
        $first = $this->awarding()->award('first-aid', 'Ada', 'ada@example.org', new Attainment(80));
        $this->clock->modify('2028-03-01 UTC');

        self::assertSame('2029-03-01', $this->awarding()->recertify($first->code->value, 'ada@example.org', new Attainment(80))->validUntil?->format('Y-m-d'));
    }

    public function testACertificateOfACourseRetiredOrEndedIsNotRenewed(): void
    {
        $this->course(endsAt: new DateTimeImmutable('2027-06-01 UTC'));
        $first = $this->awarding()->award('first-aid', 'Ada', 'ada@example.org', new Attainment(80));
        $this->clock->modify('2027-06-01 UTC');

        try {
            $this->awarding()->recertify($first->code->value, 'ada@example.org', new Attainment(80));
            self::fail('Ended.');
        } catch (CertificateRefused $certificateRefused) {
            self::assertSame('The course "first-aid" has ended.', $certificateRefused->getMessage());
        }

        $this->courses->courses['first-aid']->retire();

        $this->expectException(CertificateRefused::class);
        $this->expectExceptionMessageIsOrContains('The course "first-aid" is retired.');

        $this->awarding()->recertify($first->code->value, 'ada@example.org', new Attainment(80));
    }

    public function testARenewalWithoutADispatcherDispatchesNothing(): void
    {
        $this->course();
        $first = $this->awarding()->award('first-aid', 'Ada', 'ada@example.org', new Attainment(80));
        $this->events->events = [];

        $renewal = $this->awarding(dispatching: false)->recertify($first->code->value, 'ada@example.org', new Attainment(80));

        self::assertSame($first, $renewal->renews);
        self::assertSame([], $this->events->events);
    }

    public function testARenewalWhoseIndexWasTakenIsAwardedAgainFromTheCertificateReadAgain(): void
    {
        $this->course();
        $first = $this->awarding()->award('first-aid', 'Ada', 'ada@example.org', new Attainment(80));
        $certificates = new LockWatchingCertificates(new LockFactory(new InMemoryStore()), $this->certificates);
        $certificates->taken = 1;

        $this->events->events = [];

        $renewal = $this->awarding(certificates: $certificates)->recertify($first->code->value, 'ada@example.org', new Attainment(80));

        self::assertSame($first, $renewal->renews);
        self::assertSame($renewal, $this->certificates->findRenewalOf($first));
        self::assertEquals([new CertificateAwarded($renewal->id, $renewal->code->value, 'first-aid'), new CertificateRenewed($renewal->id, $renewal->code->value, 'first-aid', $first->id)], $this->events->events);
    }

    public function testARenewalWhoseCertificateDisappearedWhileItsIndexWasTakenIsRefused(): void
    {
        $this->course();
        $first = $this->awarding()->award('first-aid', 'Ada', 'ada@example.org', new Attainment(80));
        $certificates = new LockWatchingCertificates(new LockFactory(new InMemoryStore()), $this->certificates);
        $certificates->taken = 1;
        $certificates->forget = true;

        try {
            $this->awarding(certificates: $certificates)->recertify($first->code->value, 'ada@example.org', new Attainment(80));
            self::fail('The certificate to renew is gone.');
        } catch (CertificateRefused $certificateRefused) {
            self::assertSame(\sprintf('There is no certificate %s.', $first->code->formatted()), $certificateRefused->getMessage());
        }
    }

    public function testTheCredentialIsReadUpToSixtyFourLevelsOfDepth(): void
    {
        $this->course();
        $document = $this->awarding(securing: Securing::DATA_INTEGRITY)->award('first-aid', 'Ada', 'https://ada.example.org', new Attainment(80));
        $credential = json_decode($document->credential, true, 64, \JSON_THROW_ON_ERROR);
        self::assertIsArray($credential);

        foreach ([64 => 'ok', 65 => 'too deep'] as $depth => $outcome) {
            $padding = [];

            for ($level = 3; $level < $depth; ++$level) {
                $padding = [$padding];
            }

            $certificate = Certificate::award('0190f1d2-3c4b-7a8e-9f00-11223344560'.($depth - 64), VerificationCode::generate(), $this->courses->courses['first-aid'], 'Ada', new DateTimeImmutable('2026-11-08 UTC'), 20 + $depth, json_encode([...$credential, 'padding' => $padding], \JSON_THROW_ON_ERROR));
            $this->certificates->save($certificate);

            try {
                $renewal = $this->awarding()->recertify($certificate->code->value, 'https://ada.example.org', new Attainment(90));
                self::assertSame('ok', $outcome);
                self::assertSame($certificate, $renewal->renews);
            } catch (CertificateRefused $certificateRefused) {
                self::assertSame('too deep', $outcome);
                self::assertStringStartsWith('The identity given is not the recipient', $certificateRefused->getMessage());
            }
        }
    }

    private function course(?Credits $credits = null, ?DateTimeImmutable $endsAt = null): void
    {
        $this->courses->save(Course::define('first-aid', 'First aid', 'The gestures that save lives.', 'Score 70 % or more.', new PassCriteria(minimumScore: 70), 12, null, $credits, $endsAt));
    }

    private function record(string $member, string $credits, string $completedAt, ?string $course = null): void
    {
        $this->activities->save(LearningActivity::record(MemberId::fromString($member), 'Workshop', Credits::fromString($credits), new DateTimeImmutable($completedAt.' UTC'), null === $course ? null : $this->courses->courses[$course]));
    }

    private function awarding(bool $activities = true, string $securing = Securing::JWT, ?CertificateRepository $certificates = null, bool $dispatching = true): Awarding
    {
        return new Awarding(
            new Courses($this->courses),
            $certificates ?? $this->certificates,
            new CredentialIssuer(Kit::issuer(), Kit::signer(), Kit::METHOD),
            new Securing(Kit::signer(), Kit::METHOD, $securing, $this->clock),
            new FixedUrls(),
            $this->clock,
            new StatusListLayout('awards', 8),
            null,
            $dispatching ? $this->events : null,
            $activities ? $this->activities : null,
        );
    }
}
