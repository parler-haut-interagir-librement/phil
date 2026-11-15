<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\CertificatesBundle\Application\Awarding;
use Phil\CertificatesBundle\Application\Courses;
use Phil\CertificatesBundle\Application\StatusListLayout;
use Phil\CertificatesBundle\Domain\Contract\CertificateRepository;
use Phil\CertificatesBundle\Domain\Contract\CourseCompletions;
use Phil\CertificatesBundle\Domain\Event\CertificateAwarded;
use Phil\CertificatesBundle\Domain\Exception\CertificateRefused;
use Phil\CertificatesBundle\Domain\Exception\InvalidValue;
use Phil\CertificatesBundle\Domain\ValueObject\Attainment;
use Phil\CertificatesBundle\Domain\ValueObject\PassCriteria;
use Phil\CertificatesBundle\Tests\Unit\Support\FixedCompletions;
use Phil\CertificatesBundle\Tests\Unit\Support\FixedUrls;
use Phil\CertificatesBundle\Tests\Unit\Support\InMemoryCertificates;
use Phil\CertificatesBundle\Tests\Unit\Support\InMemoryCourses;
use Phil\CertificatesBundle\Tests\Unit\Support\Kit;
use Phil\CertificatesBundle\Tests\Unit\Support\LockWatchingCertificates;
use Phil\CertificatesBundle\Tests\Unit\Support\RecordedEvents;
use Phil\OpenBadges\CredentialIssuer;
use Phil\OpenBadgesBundle\Application\Securing;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * Certificates of a course that names a completion source, awarded on what `CourseCompletions` answers.
 */
#[CoversClass(Awarding::class)]
#[CoversClass(Courses::class)]
final class AwardingOnCompletionTest extends TestCase
{
    private InMemoryCourses $courses;

    private InMemoryCertificates $certificates;

    private RecordedEvents $events;

    #[Override]
    protected function setUp(): void
    {
        $this->courses = new InMemoryCourses();
        $this->certificates = new InMemoryCertificates();
        $this->events = new RecordedEvents();
    }

    public function testACourseWithoutACompletionSourceNeverAsksIt(): void
    {
        $completions = new FixedCompletions(['first-aid' => ['member-42' => 'enrolment-7']]);
        $certificate = $this->awarding(null, $completions)->award('first-aid', 'Ada', 'ada@example.org', new Attainment(70), holder: 'member-42');

        self::assertNull($certificate->completion);
        self::assertSame([], $completions->asked);
    }

    public function testTheCompletionAnsweredIsKeptAsTheEvidenceOfTheCertificate(): void
    {
        $completions = new FixedCompletions(['fa-2027' => ['member-42' => 'enrolment-7']]);
        $certificate = $this->awarding('fa-2027', $completions)->award('first-aid', 'Ada', 'ada@example.org', new Attainment(70), holder: 'member-42');

        self::assertSame(['enrolment-7', 'member-42'], [$certificate->completion, $certificate->holder]);
        self::assertSame([['fa-2027', 'member-42']], $completions->asked);
        self::assertSame($certificate, $this->certificates->findByCompletion($certificate->course, 'enrolment-7'));
        self::assertEquals([new CertificateAwarded($certificate->id, $certificate->code->value, 'first-aid')], $this->events->events);
    }

    /**
     * @return iterable<string, array{?string, bool, ?string, string}>
     */
    public static function refusals(): iterable
    {
        yield 'a holder who has not completed it' => ['member-42', true, null, 'The holder has not completed the course "first-aid".'];
        yield 'no holder' => [null, true, 'enrolment-7', 'The course "first-aid" is awarded on completion: the award names the holder whose completion is asked.'];
        yield 'no source of completions' => ['member-42', false, 'enrolment-7', 'The course "first-aid" is awarded on completion, and no source of completions is configured.'];
        yield 'an empty identifier' => ['member-42', true, '', 'The completion source of the course "first-aid" answered an identifier of a completion that cannot be kept.'];
        yield 'an identifier too long' => ['member-42', true, str_repeat('c', 256), 'The completion source of the course "first-aid" answered an identifier of a completion that cannot be kept.'];
        yield 'an identifier with a space' => ['member-42', true, 'enrolment 7', 'The completion source of the course "first-aid" answered an identifier of a completion that cannot be kept.'];
    }

    #[DataProvider('refusals')]
    public function testAnAwardOnCompletionIsRefusedWithoutACompletionToKeep(?string $holder, bool $configured, ?string $answer, string $message): void
    {
        $completions = new FixedCompletions(null === $answer ? [] : ['fa-2027' => ['member-42' => $answer]]);

        try {
            $this->awarding('fa-2027', $configured ? $completions : null)->award('first-aid', 'Ada', 'ada@example.org', new Attainment(70), holder: $holder);
            self::fail('The award is refused.');
        } catch (CertificateRefused $certificateRefused) {
            self::assertSame($message, $certificateRefused->getMessage());
        }

        self::assertSame(0, $this->certificates->saved);
        self::assertSame([], $this->events->events);
        self::assertSame(null !== $holder && $configured ? [['fa-2027', 'member-42']] : [], $completions->asked);
    }

    public function testAnIdentifierAtItsLongestIsKept(): void
    {
        $completions = new FixedCompletions(['fa-2027' => ['member-42' => str_repeat('c', 255)]]);

        self::assertSame(str_repeat('c', 255), $this->awarding('fa-2027', $completions)->award('first-aid', 'Ada', 'ada@example.org', new Attainment(70), holder: 'member-42')->completion);
    }

    public function testACompletionIsAwardedOneCertificate(): void
    {
        $awarding = $this->awarding('fa-2027', new FixedCompletions(['fa-2027' => ['member-42' => 'enrolment-7']]));
        $first = $awarding->award('first-aid', 'Ada', 'ada@example.org', new Attainment(70), holder: 'member-42');

        try {
            $awarding->award('first-aid', 'Ada', 'ada@example.org', new Attainment(70), holder: 'member-42');
            self::fail('The completion is already awarded.');
        } catch (CertificateRefused $certificateRefused) {
            self::assertSame(\sprintf('The certificate %s of the course "first-aid" was already awarded on that completion.', $first->code->formatted()), $certificateRefused->getMessage());
        }

        self::assertCount(1, $this->certificates->certificates);
        self::assertSame(1, $this->dispatched());
    }

    public function testAnAwardOnCompletionGivesBackTheCertificateAlreadyAwardedOnIt(): void
    {
        $completions = new FixedCompletions(['fa-2027' => ['member-42' => 'enrolment-7', 'member-43' => 'enrolment-8']]);
        $awarding = $this->awarding('fa-2027', $completions);

        $first = $awarding->awardOnCompletion('first-aid', 'member-42', 'Ada', 'ada@example.org', new Attainment(70), listed: true);
        self::assertSame(['enrolment-7', 'member-42', true], [$first->completion, $first->holder, $first->listed]);
        self::assertEquals([new CertificateAwarded($first->id, $first->code->value, 'first-aid')], $this->events->events);

        self::assertSame($first, $awarding->awardOnCompletion('first-aid', 'member-42', 'Ada', 'ada@example.org', new Attainment(70)));
        self::assertSame(1, $this->certificates->saved);
        self::assertSame(1, $this->dispatched());

        $other = $awarding->awardOnCompletion('first-aid', 'member-43', 'Grace', 'grace@example.org', new Attainment(70));
        self::assertSame(['enrolment-8', false], [$other->completion, $other->listed]);
        self::assertSame(2, $this->dispatched());
    }

    public function testAnAwardOnCompletionNeedsACompletionSourceTheCriteriaAndAStableHolder(): void
    {
        try {
            $this->awarding(null, new FixedCompletions())->awardOnCompletion('first-aid', 'member-42', 'Ada', 'ada@example.org', new Attainment(70));
            self::fail('The course names no completion source.');
        } catch (CertificateRefused $certificateRefused) {
            self::assertSame('The course "first-aid" names no completion source: its certificates are awarded by award().', $certificateRefused->getMessage());
        }

        $completions = new FixedCompletions(['fa-2027' => ['member-42' => 'enrolment-7']]);

        try {
            $this->awarding('fa-2027', $completions)->awardOnCompletion('first-aid', 'member-42', 'Ada', 'ada@example.org', new Attainment(69));
            self::fail('The criteria are not met.');
        } catch (CertificateRefused $certificateRefused) {
            self::assertSame('The criteria of the course "first-aid" are not met: score.', $certificateRefused->getMessage());
        }

        self::assertSame([], $completions->asked);

        $this->expectException(InvalidValue::class);
        $this->awarding('fa-2027', $completions)->awardOnCompletion('first-aid', 'ada@example.org', 'Ada', 'ada@example.org', new Attainment(70));
    }

    public function testAnAwardOnCompletionOfARetiredCourseIsRefused(): void
    {
        $awarding = $this->awarding('fa-2027', new FixedCompletions(['fa-2027' => ['member-42' => 'enrolment-7']]));
        $this->courses->courses['first-aid']->retire();

        $this->expectException(CertificateRefused::class);
        $this->expectExceptionMessageIsOrContains('The course "first-aid" is retired.');

        $awarding->awardOnCompletion('first-aid', 'member-42', 'Ada', 'ada@example.org', new Attainment(70));
    }

    public function testTheCompletionIsLookedForUnderTheLockOfTheStatusIndex(): void
    {
        $locks = new LockFactory(new InMemoryStore());
        $certificates = new LockWatchingCertificates($locks);
        $awarding = $this->awarding('fa-2027', new FixedCompletions(['fa-2027' => ['member-42' => 'enrolment-7']]), $certificates, $locks);

        $first = $awarding->awardOnCompletion('first-aid', 'member-42', 'Ada', 'ada@example.org', new Attainment(70));
        self::assertSame(['completion:held', 'next:held', 'save:held'], $certificates->held);

        self::assertSame($first, $awarding->awardOnCompletion('first-aid', 'member-42', 'Ada', 'ada@example.org', new Attainment(70)));
        self::assertSame(['completion:held', 'next:held', 'save:held', 'completion:held'], $certificates->held);
        self::assertFalse($certificates->isHeld());
    }

    public function testAnAwardWhoseIndexWasTakenLooksForTheCompletionAgain(): void
    {
        $locks = new LockFactory(new InMemoryStore());
        $certificates = new LockWatchingCertificates($locks);
        $certificates->taken = 1;

        $awarding = $this->awarding('fa-2027', new FixedCompletions(['fa-2027' => ['member-42' => 'enrolment-7']]), $certificates, $locks);

        $certificate = $awarding->awardOnCompletion('first-aid', 'member-42', 'Ada', 'ada@example.org', new Attainment(70));

        self::assertSame('enrolment-7', $certificate->completion);
        self::assertSame(['completion:held', 'next:held', 'save:held', 'completion:held', 'next:held', 'save:held'], $certificates->held);
        self::assertSame(1, $this->dispatched());
    }

    public function testARecertificationAsksNoCompletion(): void
    {
        $completions = new FixedCompletions(['fa-2027' => ['member-42' => 'enrolment-7']]);
        $awarding = $this->awarding('fa-2027', $completions);
        $first = $awarding->award('first-aid', 'Ada', 'ada@example.org', new Attainment(70), holder: 'member-42');

        $renewal = $awarding->recertify($first->code->value, 'ada@example.org', new Attainment(90));

        self::assertNull($renewal->completion);
        self::assertSame($first, $renewal->renews);
        self::assertCount(1, $completions->asked);
    }

    public function testCoursesNameAndChangeTheirCompletionSource(): void
    {
        $repository = new InMemoryCourses();
        $courses = new Courses($repository);

        self::assertSame('fa-2027', $courses->define('first-aid', 'First aid', '', 'Pass.', completionSource: 'fa-2027')->completionSource);
        self::assertSame(1, $repository->saved);
        self::assertSame('fa-2028', $courses->changeCompletionSource('first-aid', 'fa-2028')->completionSource);
        self::assertSame(2, $repository->saved);
        self::assertSame('fa-2028', $courses->get('first-aid')->completionSource);
        self::assertNull($courses->changeCompletionSource('first-aid', null)->completionSource);

        $this->expectException(CertificateRefused::class);
        $courses->changeCompletionSource('cpr', 'cpr');
    }

    private function dispatched(): int
    {
        return \count($this->events->events);
    }

    private function awarding(?string $source, ?CourseCompletions $completions, ?CertificateRepository $certificates = null, ?LockFactory $locks = null): Awarding
    {
        $clock = new MockClock(new DateTimeImmutable('2026-11-08 10:00:00 UTC'));
        $this->courses = new InMemoryCourses();
        $course = Kit::firstAid(12, new PassCriteria(minimumScore: 70));
        $course->changeCompletionSource($source);

        $this->courses->save($course);
        $this->certificates = new InMemoryCertificates();
        $this->events = new RecordedEvents();

        return new Awarding(
            new Courses($this->courses),
            $certificates ?? $this->certificates,
            new CredentialIssuer(Kit::issuer(), Kit::signer(), Kit::METHOD),
            new Securing(Kit::signer(), Kit::METHOD, Securing::JWT, $clock),
            new FixedUrls(),
            $clock,
            new StatusListLayout('awards', 2),
            $locks,
            $this->events,
            null,
            $completions,
        );
    }
}
