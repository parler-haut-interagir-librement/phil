<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\CertificatesBundle\Application\Awarding;
use Phil\CertificatesBundle\Application\ContinuingEducation;
use Phil\CertificatesBundle\Application\Courses;
use Phil\CertificatesBundle\Application\ExpiryReminders;
use Phil\CertificatesBundle\Application\StatusListLayout;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\Model\Course;
use Phil\CertificatesBundle\Domain\ValueObject\Attainment;
use Phil\CertificatesBundle\Domain\ValueObject\Credits;
use Phil\CertificatesBundle\Domain\ValueObject\PassCriteria;
use Phil\CertificatesBundle\Infrastructure\Console\AwardCommand;
use Phil\CertificatesBundle\Infrastructure\Console\CreditCommand;
use Phil\CertificatesBundle\Infrastructure\Console\CreditsCommand;
use Phil\CertificatesBundle\Infrastructure\Console\Numbers;
use Phil\CertificatesBundle\Infrastructure\Console\RecertifyCommand;
use Phil\CertificatesBundle\Infrastructure\Console\RemindCommand;
use Phil\CertificatesBundle\Infrastructure\Console\StatusCommand;
use Phil\CertificatesBundle\Tests\Unit\Support\FixedUrls;
use Phil\CertificatesBundle\Tests\Unit\Support\InMemoryActivities;
use Phil\CertificatesBundle\Tests\Unit\Support\InMemoryCertificates;
use Phil\CertificatesBundle\Tests\Unit\Support\InMemoryCourses;
use Phil\CertificatesBundle\Tests\Unit\Support\Kit;
use Phil\CertificatesBundle\Tests\Unit\Support\RecordedEvents;
use Phil\OpenBadges\CredentialIssuer;
use Phil\OpenBadgesBundle\Application\Securing;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The commands of recertification, reminders and continuing education.
 */
#[CoversClass(RecertifyCommand::class)]
#[CoversClass(RemindCommand::class)]
#[CoversClass(CreditCommand::class)]
#[CoversClass(CreditsCommand::class)]
#[CoversClass(AwardCommand::class)]
#[CoversClass(StatusCommand::class)]
#[CoversClass(Numbers::class)]
final class LifecycleConsoleTest extends TestCase
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
        $this->courses->save(Course::define('first-aid', 'First aid', '', 'Score 70 %.', new PassCriteria(minimumScore: 70), 12, null, Credits::fromString('3')));
        $this->courses->save(Course::define('cpr', 'CPR', '', 'Score 70 %.', new PassCriteria(minimumScore: 70), 12));

        $this->certificates = new InMemoryCertificates();
        $this->activities = new InMemoryActivities();
        $this->events = new RecordedEvents();
        $this->clock = new MockClock(new DateTimeImmutable('2026-11-08 10:00:00 UTC'));
    }

    public function testTheAwardNamesTheHolder(): void
    {
        $tester = new CommandTester(new AwardCommand($this->awarding(), new FixedUrls()));
        $tester->execute(['course' => 'first-aid', 'name' => 'Ada', 'identity' => 'ada@example.org', '--score' => '80', '--holder' => 'member-1']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame('member-1', $this->only()->holder);
    }

    public function testARecertificationByCreditsPrintsTheNewCode(): void
    {
        $first = $this->awarding()->award('first-aid', 'Ada', 'ada@example.org', new Attainment(80), holder: 'member-1');
        $credit = $this->execute(new CreditCommand($this->education()), ['member' => 'member-1', 'title' => 'Workshop', 'credits' => '3', '--completed' => '2027-01-15', '--course' => 'first-aid', '--source' => 'workshop-1']);
        $activity = array_first($this->activities->activities);
        self::assertNotNull($activity);
        self::assertSame([Command::SUCCESS, \sprintf("Recorded 3 credits for member-1: %s.\n", $activity->id)], [$credit->getStatusCode(), $credit->getDisplay()]);
        self::assertSame(['2027-01-15', 'first-aid', 'workshop-1'], [$activity->completedAt->format('Y-m-d'), $activity->course?->slug, $activity->source]);
        $this->clock->modify('2027-10-01 UTC');

        $tester = $this->execute(new RecertifyCommand($this->awarding(), new FixedUrls()), ['code' => $first->code->formatted(), 'identity' => 'ada@example.org']);

        $renewal = $this->certificates->findRenewalOf($first);
        self::assertInstanceOf(Certificate::class, $renewal);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame(\sprintf("Renewed the certificate %s with %s, valid until 2028-11-08.\nhttps://example.com/certificates/%s\n", $first->code->formatted(), $renewal->code->formatted(), $renewal->code->value), $tester->getDisplay());
        self::assertStringNotContainsString('ada@example.org', $tester->getDisplay());

        $status = $this->execute(new StatusCommand($this->certificates, $this->clock), ['code' => $first->code->value]);
        self::assertStringContainsString(\sprintf("holder             member-1\nrenewed_by         %s\n", $renewal->code->formatted()), $status->getDisplay());
        $status = $this->execute(new StatusCommand($this->certificates, $this->clock), ['code' => $renewal->code->value]);
        self::assertStringContainsString(\sprintf("renews             %s\n", $first->code->formatted()), $status->getDisplay());
    }

    public function testARecertificationByAnAttainmentAndItsRefusals(): void
    {
        $first = $this->awarding()->award('cpr', 'Ada', 'ada@example.org', new Attainment(80));
        $command = new RecertifyCommand($this->awarding(), new FixedUrls());

        $refused = $this->execute($command, ['code' => $first->code->value, 'identity' => 'ada@example.org']);
        self::assertSame([Command::FAILURE, "The course \"cpr\" asks for no credits: a recertification shows an attainment that meets its criteria.\n"], [$refused->getStatusCode(), $refused->getDisplay()]);

        $invalid = $this->execute($command, ['code' => $first->code->value, 'identity' => 'ada@example.org', '--score' => 'high']);
        self::assertSame([Command::INVALID, "The score is a whole number; \"high\" is not.\n"], [$invalid->getStatusCode(), $invalid->getDisplay()]);

        $unmet = $this->execute($command, ['code' => $first->code->value, 'identity' => 'ada@example.org', '--minutes' => '60', '-m' => ['cpr']]);
        self::assertSame([Command::FAILURE, "The criteria of the course \"cpr\" are not met: score.\n"], [$unmet->getStatusCode(), $unmet->getDisplay()]);

        $renewed = $this->execute($command, ['code' => $first->code->value, 'identity' => 'ada@example.org', '--score' => '90']);
        self::assertSame(Command::SUCCESS, $renewed->getStatusCode());
    }

    public function testAnEndlessRenewalSaysSo(): void
    {
        $this->courses->save(Course::define('cpr', 'CPR', '', 'Score.', new PassCriteria(minimumScore: 70)));
        $first = $this->awarding()->award('cpr', 'Ada', 'ada@example.org', new Attainment(80));

        $tester = $this->execute(new RecertifyCommand($this->awarding(), new FixedUrls()), ['code' => $first->code->value, 'identity' => 'ada@example.org', '--score' => '90']);

        self::assertStringContainsString(', valid until its course ends.', $tester->getDisplay());
    }

    public function testTheRemindersAreDispatchedAndCounted(): void
    {
        $this->awarding()->award('cpr', 'Ada', 'ada@example.org', new Attainment(80));
        $this->awarding()->award('cpr', 'Grace', 'grace@example.org', new Attainment(80));
        $this->clock->modify('2027-10-01 UTC');
        $command = new RemindCommand(new ExpiryReminders($this->certificates, $this->clock, $this->events));

        self::assertSame("Dispatched 1 reminder.\n", $this->execute($command, ['--limit' => '1'])->getDisplay());
        self::assertSame("Dispatched 1 reminder.\n", $this->execute($command, ['--limit' => '0'])->getDisplay());
        self::assertSame("Dispatched 0 reminders.\n", $this->execute($command, [])->getDisplay());
        $this->clock->modify('2027-10-20 UTC');
        self::assertSame("Dispatched 2 reminders.\n", $this->execute($command, [])->getDisplay());

        $invalid = $this->execute($command, ['--limit' => 'all']);
        self::assertSame([Command::INVALID, "The limit is a whole number; \"all\" is not.\n"], [$invalid->getStatusCode(), $invalid->getDisplay()]);
    }

    public function testCreditsAreShownForAMemberOverAPeriod(): void
    {
        $education = $this->education();
        $education->record('member-1', 'General', Credits::fromString('1.5'), new DateTimeImmutable('2026-03-01 UTC'));
        $education->record('member-1', 'CPR refresher', Credits::fromString('2'), new DateTimeImmutable('2026-06-01 UTC'), 'cpr');
        $education->record('member-1', 'First aid', Credits::fromString('4'), new DateTimeImmutable('2026-07-01 UTC'), 'first-aid');
        $education->record('member-1', 'Long ago', Credits::fromString('8'), new DateTimeImmutable('2025-11-07 UTC'));

        $text = $this->execute($this->creditsCommand(), ['member' => 'member-1']);
        self::assertSame(Command::SUCCESS, $text->getStatusCode());
        self::assertSame(
            "member     member-1\n"
            ."from       2025-11-08\n"
            ."until      2026-11-09\n"
            ."total      7.5\n"
            ."  2026-07-01       4  First aid\n"
            ."  2026-06-01       2  CPR refresher\n"
            ."  2026-03-01     1.5  General\n",
            $text->getDisplay(),
        );

        $json = $this->execute($this->creditsCommand(), ['member' => 'member-1', '--from' => '2026-05-01', '--until' => '2026-12-01', '--course' => 'cpr', '--format' => 'json']);
        $fields = json_decode($json->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($fields);
        self::assertSame(['member-1', '2026-05-01', '2026-12-01', '2'], [$fields['member'], $fields['from'], $fields['until'], $fields['total']]);
        self::assertIsArray($fields['activities']);
        self::assertCount(1, $fields['activities']);
        self::assertIsArray($fields['activities'][0]);
        self::assertSame(['CPR refresher', '2', '2026-06-01', 'cpr'], [$fields['activities'][0]['title'], $fields['activities'][0]['credits'], $fields['activities'][0]['completed_at'], $fields['activities'][0]['course']]);
        self::assertStringContainsString("\n    \"member\": \"member-1\",\n", $json->getDisplay());
        self::assertIsString($fields['activities'][0]['id']);
        self::assertSame('CPR refresher', $this->activities->find($fields['activities'][0]['id'])?->title);
    }

    public function testTheJsonIsNotEscapedAndTheDaysAreWholeDays(): void
    {
        $education = $this->education();
        $education->record('member-1', 'Café / réunion', Credits::fromString('1'), new DateTimeImmutable('2025-11-08 00:00:00 UTC'));
        $education->record('member-1', 'Too late', Credits::fromString('2'), new DateTimeImmutable('2026-11-09 00:00:00 UTC'));
        $education->record('member-1', 'Too early', Credits::fromString('4'), new DateTimeImmutable('2025-11-07 23:59:59 UTC'));

        $text = $this->execute($this->creditsCommand(), ['member' => 'member-1']);
        $json = $this->execute($this->creditsCommand(), ['member' => 'member-1', '--format' => 'json']);

        self::assertStringContainsString('"title": "Café / réunion"', $json->getDisplay());
        self::assertStringContainsString("total      1\n", $text->getDisplay());
        self::assertStringNotContainsString('Too late', $text->getDisplay());
        self::assertStringNotContainsString('Too early', $text->getDisplay());
    }

    public function testTheProgressOfACertificate(): void
    {
        $certificate = $this->awarding()->award('first-aid', 'Ada', 'ada@example.org', new Attainment(80), holder: 'member-1');
        $this->education()->record('member-1', 'Workshop', Credits::fromString('1.25'));

        $tester = $this->execute($this->creditsCommand(), ['--certificate' => $certificate->code->formatted()]);

        self::assertSame("gathered   1.25\nrequired   3\nmissing    1.75\n", $tester->getDisplay());

        $none = $this->execute($this->creditsCommand(), ['--certificate' => $this->awarding()->award('cpr', 'Ada', 'ada@example.org', new Attainment(80), holder: 'member-1')->code->value]);
        self::assertSame("gathered   1.25\n", $none->getDisplay());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, int, string}>
     */
    public static function refusedCredits(): iterable
    {
        yield 'no member' => [[], Command::INVALID, "Give the member, or --certificate=<code>.\n"];
        yield 'a day that is not one' => [['member' => 'member-1', '--from' => '2026-02-30'], Command::INVALID, "The first day is a day written YYYY-MM-DD; \"2026-02-30\" is not.\n"];
        yield 'a period backwards' => [['member' => 'member-1', '--from' => '2026-02-01', '--until' => '2026-01-01'], Command::INVALID, "A period ends after it starts.\n"];
        yield 'an unknown certificate' => [['--certificate' => '0000-0000-0000'], Command::FAILURE, "There is no certificate 0000-0000-0000.\n"];
        yield 'an e-mail address' => [['member' => 'ada@example.org'], Command::INVALID, "The identifier of a member is a stable identifier, not an e-mail address.\n"];
    }

    /**
     * @param array<string, mixed> $input
     */
    #[DataProvider('refusedCredits')]
    public function testCreditsThatCannotBeShown(array $input, int $status, string $message): void
    {
        $tester = $this->execute($this->creditsCommand(), $input);

        self::assertSame([$status, $message], [$tester->getStatusCode(), $tester->getDisplay()]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, int, string}>
     */
    public static function refusedActivities(): iterable
    {
        yield 'nothing' => [[], Command::INVALID, "Give the member, the title and the credits of the activity, or --remove=<id>.\n"];
        yield 'no credits' => [['member' => 'member-1', 'title' => 'Workshop'], Command::INVALID, "Give the member, the title and the credits of the activity, or --remove=<id>.\n"];
        yield 'credits that cannot be read' => [['member' => 'member-1', 'title' => 'Workshop', 'credits' => 'three'], Command::INVALID, "Credits are a number with up to two decimals, such as 1.5; \"three\" is not.\n"];
        yield 'a day that is not one' => [['member' => 'member-1', 'title' => 'Workshop', 'credits' => '1', '--completed' => '15/01/2027'], Command::INVALID, "The day completed is a day written YYYY-MM-DD; \"15/01/2027\" is not.\n"];
        yield 'an unknown course' => [['member' => 'member-1', 'title' => 'Workshop', 'credits' => '1', '--course' => 'alchemy'], Command::FAILURE, "There is no course \"alchemy\".\n"];
        yield 'an unknown activity' => [['--remove' => 'nope'], Command::FAILURE, "There is no activity nope.\n"];
    }

    /**
     * @param array<string, mixed> $input
     */
    #[DataProvider('refusedActivities')]
    public function testActivitiesThatCannotBeRecorded(array $input, int $status, string $message): void
    {
        $tester = $this->execute(new CreditCommand($this->education()), $input);

        self::assertSame([$status, $message], [$tester->getStatusCode(), $tester->getDisplay()]);
        self::assertSame([], $this->activities->activities);
    }

    public function testAnActivityIsRecordedNowAndRemoved(): void
    {
        $this->execute(new CreditCommand($this->education()), ['member' => 'member-1', 'title' => 'Workshop', 'credits' => '0,5']);
        $activity = array_first($this->activities->activities);
        self::assertNotNull($activity);
        self::assertSame(['2026-11-08 10:00', null, null, 50], [$activity->completedAt->format('Y-m-d H:i'), $activity->course, $activity->source, $activity->credits->hundredths]);

        $tester = $this->execute(new CreditCommand($this->education()), ['--remove' => $activity->id]);

        self::assertSame([Command::SUCCESS, \sprintf("Removed the activity %s, \"Workshop\", of member-1.\n", $activity->id)], [$tester->getStatusCode(), $tester->getDisplay()]);
        self::assertSame([], $this->activities->activities);
    }

    public function testTheCommandsAreNamed(): void
    {
        self::assertSame('phil:certificates:recertify', new RecertifyCommand($this->awarding(), new FixedUrls())->getName());
        self::assertSame('phil:certificates:remind', new RemindCommand(new ExpiryReminders($this->certificates, $this->clock, $this->events))->getName());
        self::assertSame('phil:certificates:credit', new CreditCommand($this->education())->getName());
        self::assertSame('phil:certificates:credits', $this->creditsCommand()->getName());
    }

    private function only(): Certificate
    {
        self::assertCount(1, $this->certificates->certificates);
        $certificate = array_first($this->certificates->certificates);
        self::assertInstanceOf(Certificate::class, $certificate);

        return $certificate;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function execute(Command $command, array $input): CommandTester
    {
        $tester = new CommandTester($command);
        $tester->execute($input);

        return $tester;
    }

    private function creditsCommand(): CreditsCommand
    {
        return new CreditsCommand($this->education(), $this->clock);
    }

    private function education(): ContinuingEducation
    {
        return new ContinuingEducation($this->activities, new Courses($this->courses), $this->certificates, $this->clock);
    }

    private function awarding(): Awarding
    {
        return new Awarding(
            new Courses($this->courses),
            $this->certificates,
            new CredentialIssuer(Kit::issuer(), Kit::signer(), Kit::METHOD),
            new Securing(Kit::signer(), Kit::METHOD, Securing::JWT, $this->clock),
            new FixedUrls(),
            $this->clock,
            new StatusListLayout('awards', 8),
            activities: $this->activities,
        );
    }
}
