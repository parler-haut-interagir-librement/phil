<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\CertificatesBundle\Application\Awarding;
use Phil\CertificatesBundle\Application\Courses;
use Phil\CertificatesBundle\Application\Revocation;
use Phil\CertificatesBundle\Application\StatusListLayout;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\ValueObject\PassCriteria;
use Phil\CertificatesBundle\Infrastructure\Console\AwardCommand;
use Phil\CertificatesBundle\Infrastructure\Console\ExportCommand;
use Phil\CertificatesBundle\Infrastructure\Console\Numbers;
use Phil\CertificatesBundle\Infrastructure\Console\RevokeCommand;
use Phil\CertificatesBundle\Infrastructure\Console\StatusCommand;
use Phil\CertificatesBundle\Tests\Unit\Support\FixedCompletions;
use Phil\CertificatesBundle\Tests\Unit\Support\FixedUrls;
use Phil\CertificatesBundle\Tests\Unit\Support\InMemoryCertificates;
use Phil\CertificatesBundle\Tests\Unit\Support\InMemoryCourses;
use Phil\CertificatesBundle\Tests\Unit\Support\Kit;
use Phil\OpenBadges\CredentialIssuer;
use Phil\OpenBadgesBundle\Application\Securing;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(AwardCommand::class)]
#[CoversClass(RevokeCommand::class)]
#[CoversClass(StatusCommand::class)]
#[CoversClass(Numbers::class)]
#[CoversClass(ExportCommand::class)]
final class ConsoleTest extends TestCase
{
    private InMemoryCertificates $certificates;

    private MockClock $clock;

    #[Override]
    protected function setUp(): void
    {
        $this->certificates = new InMemoryCertificates();
        $this->clock = new MockClock(new DateTimeImmutable('2026-11-08 10:00:00 UTC'));
    }

    public function testTheAwardPrintsTheCodeAndTheAddressButNeverTheIdentity(): void
    {
        $tester = $this->award(['course' => 'first-aid', 'name' => 'Ada Lovelace', 'identity' => 'ada@example.org', '--score' => '82', '--minutes' => '240', '--module' => ['cpr', 'aed'], '--listed' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $certificate = $this->awarded();
        self::assertTrue($certificate->listed);
        self::assertSame(
            \sprintf("Awarded the certificate %s of \"first-aid\" to Ada Lovelace.\nhttps://example.com/certificates/%s\n", $certificate->code->formatted(), $certificate->code->value),
            $tester->getDisplay(),
        );
        self::assertStringNotContainsString('ada@example.org', $tester->getDisplay());
    }

    public function testAnAwardIsUnlistedWithoutTheOption(): void
    {
        $tester = $this->award(['course' => 'first-aid', 'name' => 'Ada', 'identity' => 'ada@example.org', '--score' => '70', '--minutes' => '60', '-m' => ['cpr']]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertFalse($this->awarded()->listed);
    }

    public function testAnAwardWhoseCriteriaAreNotMetFailsAndTheMinutesDefaultToNone(): void
    {
        $tester = $this->award(['course' => 'first-aid', 'name' => 'Ada', 'identity' => 'ada@example.org']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame("The criteria of the course \"first-aid\" are not met: score, minutes, module:cpr.\n", $tester->getDisplay());
        self::assertSame([], $this->certificates->certificates);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidAwards(): iterable
    {
        yield 'a score in words' => [['--score' => 'high'], 'The score is a whole number; "high" is not.'];
        yield 'a negative score' => [['--score' => '-1'], 'The score is a whole number; "-1" is not.'];
        yield 'a score with a leading zero' => [['--score' => '07'], 'The score is a whole number; "07" is not.'];
        yield 'a score above 100' => [['--score' => '101'], 'A score is from 0 to 100; 101 is not.'];
        yield 'minutes with a decimal' => [['--score' => '80', '--minutes' => '1.5'], 'The minutes is a whole number; "1.5" is not.'];
        yield 'minutes with a trailing line' => [['--score' => '80', '--minutes' => "12\n"], "The minutes is a whole number; \"12\n\" is not."];
        yield 'minutes too long' => [['--score' => '80', '--minutes' => '1234567890'], 'The minutes is a whole number; "1234567890" is not.'];
        yield 'an empty name' => [['--score' => '80', '--minutes' => '60', '--module' => ['cpr'], 'name' => ' '], 'The name on a certificate has from 1 to 255 characters.'];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('invalidAwards')]
    public function testAnAwardThatCannotBeReadIsInvalid(array $options, string $message): void
    {
        $tester = $this->award([...['course' => 'first-aid', 'name' => 'Ada', 'identity' => 'ada@example.org'], ...$options]);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertSame($message."\n", $tester->getDisplay());
    }

    public function testAnAwardOnACourseThatNamesACompletionSourcePrintsTheCompletion(): void
    {
        $tester = new CommandTester($this->awardCommand('fa-2027', new FixedCompletions(['fa-2027' => ['member-42' => 'enrolment-7']])));
        $tester->execute(['course' => 'first-aid', 'name' => 'Ada', 'identity' => 'ada@example.org', '--score' => '70', '--minutes' => '60', '-m' => ['cpr'], '--holder' => 'member-42']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $certificate = $this->awarded();
        self::assertSame(
            \sprintf("Awarded the certificate %s of \"first-aid\" to Ada.\nhttps://example.com/certificates/%s\nCompletion: enrolment-7\n", $certificate->code->formatted(), $certificate->code->value),
            $tester->getDisplay(),
        );

        self::assertStringContainsString("holder             member-42\ncompletion         enrolment-7\n", $this->showStatus(['code' => $certificate->code->value])->getDisplay());

        $tester->execute(['course' => 'first-aid', 'name' => 'Ada', 'identity' => 'ada@example.org', '--score' => '70', '--minutes' => '60', '-m' => ['cpr'], '--holder' => 'member-43']);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame("The holder has not completed the course \"first-aid\".\n", $tester->getDisplay());
    }

    public function testAnAwardOnCompletionShowsTheCertificateAlreadyAwardedOnIt(): void
    {
        $tester = new CommandTester($this->awardCommand('fa-2027', new FixedCompletions(['fa-2027' => ['member-42' => 'enrolment-7']])));
        $input = ['course' => 'first-aid', 'name' => 'Ada', 'identity' => 'ada@example.org', '--score' => '70', '--minutes' => '60', '-m' => ['cpr'], '--holder' => 'member-42', '--on-completion' => true, '--listed' => true];

        $tester->execute($input);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $certificate = $this->awarded();
        $display = \sprintf("The certificate %s of \"first-aid\" is awarded to Ada on that completion.\nhttps://example.com/certificates/%s\nCompletion: enrolment-7\n", $certificate->code->formatted(), $certificate->code->value);
        self::assertSame($display, $tester->getDisplay());
        self::assertTrue($certificate->listed);

        $tester->execute($input);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame($display, $tester->getDisplay());
        self::assertSame($certificate, $this->awarded());

        unset($input['--holder']);
        $tester->execute($input);
        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertSame("An award on completion names the holder, with --holder.\n", $tester->getDisplay());
    }

    public function testTheLargestNumberIsRead(): void
    {
        self::assertSame(999_999_999, Numbers::integer('minutes', '999999999'));
        self::assertSame(0, Numbers::integer('minutes', '0'));
    }

    public function testARevocationByTheCodeAsPrinted(): void
    {
        $this->certificates->save(Kit::awarded());

        $tester = new CommandTester(new RevokeCommand(new Revocation($this->certificates, $this->clock)));
        $tester->execute(['code' => '7kq2-m9xa-04tz', 'reason' => 'Issued in error.']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame("Revoked the certificate 7KQ2-M9XA-04TZ.\n", $tester->getDisplay());
        self::assertSame('Issued in error.', $this->certificates->certificates['7KQ2M9XA04TZ']->revocationReason);

        $tester->execute(['code' => '7KQ2M9XA04TZ', 'reason' => 'Twice.']);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame("The certificate 7KQ2-M9XA-04TZ is already revoked.\n", $tester->getDisplay());

        $tester->execute(['code' => 'nope', 'reason' => 'Fraud.']);
        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertSame("\"nope\" is not a verification code: twelve letters and digits.\n", $tester->getDisplay());
    }

    public function testTheStatusOfAValidCertificate(): void
    {
        $this->certificates->save(Kit::awarded());

        $tester = $this->showStatus(['code' => '7kq2m9xa04tz']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame(
            "code               7KQ2-M9XA-04TZ\n"
            ."status             valid\n"
            ."course             first-aid\n"
            ."name               Ada Lovelace\n"
            ."awarded_at         2026-11-08T10:00:00+00:00\n"
            ."valid_until        2027-11-08T10:00:00+00:00\n",
            $tester->getDisplay(),
        );
    }

    public function testTheStatusOfARevokedCertificateAsJson(): void
    {
        $certificate = Kit::awarded(Kit::firstAid(null));
        $certificate->revoke('Fraud.', new DateTimeImmutable('2026-12-01 12:00 UTC'));

        $this->certificates->save($certificate);

        $tester = $this->showStatus(['code' => '7KQ2-M9XA-04TZ', '--format' => 'json']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            'code' => '7KQ2-M9XA-04TZ',
            'status' => 'revoked',
            'course' => 'first-aid',
            'name' => 'Ada Lovelace',
            'awarded_at' => '2026-11-08T10:00:00+00:00',
            'valid_until' => null,
            'revoked_at' => '2026-12-01T12:00:00+00:00',
            'revocation_reason' => 'Fraud.',
            'holder' => null,
            'completion' => null,
            'renews' => null,
            'renewed_by' => null,
            'reminded_at' => null,
        ], json_decode($tester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR));
        self::assertStringContainsString("\n    \"status\": \"revoked\",\n", $tester->getDisplay());
    }

    public function testTheJsonStatusKeepsSlashesAndLettersAsTheyAre(): void
    {
        $certificate = Kit::awarded(Kit::firstAid(null));
        $certificate->revoke('Usurpation / identité', new DateTimeImmutable('2026-12-01 12:00 UTC'));

        $this->certificates->save($certificate);

        $tester = $this->showStatus(['code' => '7KQ2-M9XA-04TZ', '--format' => 'json']);

        self::assertStringContainsString('"revocation_reason": "Usurpation / identité"', $tester->getDisplay());
    }

    public function testTheStatusFollowsTheClock(): void
    {
        $this->certificates->save(Kit::awarded());
        $this->clock->modify('2027-11-08 10:00:00 UTC');

        self::assertStringContainsString("status             expired\n", $this->showStatus(['code' => '7KQ2M9XA04TZ'])->getDisplay());
    }

    public function testTheStatusOfAnUnknownOrUnreadableCode(): void
    {
        $unknown = $this->showStatus(['code' => '0000-0000-0000']);
        self::assertSame(Command::FAILURE, $unknown->getStatusCode());
        self::assertSame("There is no certificate 0000-0000-0000.\n", $unknown->getDisplay());

        $unreadable = $this->showStatus(['code' => 'short']);
        self::assertSame(Command::INVALID, $unreadable->getStatusCode());
        self::assertSame("\"short\" is not a verification code: twelve letters and digits.\n", $unreadable->getDisplay());
    }

    public function testTheExportIsTheCredentialAlone(): void
    {
        $this->certificates->save(Kit::awarded());
        $tester = new CommandTester(new ExportCommand($this->certificates));

        $tester->execute(['code' => '7kq2-m9xa-04tz'], ['capture_stderr_separately' => true]);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame('header.payload.signature', $tester->getDisplay());
        self::assertSame('', $tester->getErrorOutput());

        $tester->execute(['code' => '7KQ2M9XA04TZ'], ['capture_stderr_separately' => true, 'verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
        self::assertSame('header.payload.signature', $tester->getDisplay());
        self::assertSame("A JSON Web Token: application/vc+jwt, credential.jwt.\n", $tester->getErrorOutput());
    }

    public function testTheExportOfADocumentWithItsProofIsWrittenRaw(): void
    {
        $this->certificates->save(Kit::awarded(credential: '{"proof":{"type":"DataIntegrityProof"},"name":"<b>"}'));
        $tester = new CommandTester(new ExportCommand($this->certificates));

        $tester->execute(['code' => '7KQ2M9XA04TZ'], ['capture_stderr_separately' => true, 'verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'decorated' => true]);
        self::assertSame('{"proof":{"type":"DataIntegrityProof"},"name":"<b>"}', $tester->getDisplay());
        self::assertSame("A document with its proof: application/vc, credential.json.\n", $tester->getErrorOutput());
    }

    public function testTheExportOfAnUnknownOrUnreadableCode(): void
    {
        $tester = new CommandTester(new ExportCommand($this->certificates));

        $tester->execute(['code' => '0000-0000-0000'], ['capture_stderr_separately' => true]);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame("There is no certificate 0000-0000-0000.\n", $tester->getErrorOutput());
        self::assertSame('', $tester->getDisplay());

        $tester->execute(['code' => 'x'], ['capture_stderr_separately' => true]);
        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertSame("\"x\" is not a verification code: twelve letters and digits.\n", $tester->getErrorOutput());
    }

    public function testTheCommandsAreNamed(): void
    {
        self::assertSame('phil:certificates:export', new ExportCommand($this->certificates)->getName());
        self::assertSame('phil:certificates:status', $this->statusCommand()->getName());
        self::assertSame('phil:certificates:revoke', new RevokeCommand(new Revocation($this->certificates, $this->clock))->getName());
        self::assertSame('phil:certificates:award', $this->awardCommand()->getName());
    }

    private function awarded(): Certificate
    {
        self::assertCount(1, $this->certificates->certificates);
        $certificate = array_first($this->certificates->certificates);
        self::assertInstanceOf(Certificate::class, $certificate);

        return $certificate;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function award(array $input): CommandTester
    {
        $tester = new CommandTester($this->awardCommand());
        $tester->execute($input);

        return $tester;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function showStatus(array $input): CommandTester
    {
        $tester = new CommandTester($this->statusCommand());
        $tester->execute($input);

        return $tester;
    }

    private function statusCommand(): StatusCommand
    {
        return new StatusCommand($this->certificates, $this->clock);
    }

    private function awardCommand(?string $source = null, ?FixedCompletions $completions = null): AwardCommand
    {
        $courses = new InMemoryCourses();
        $course = Kit::firstAid(12, new PassCriteria(minimumScore: 70, minimumMinutes: 60, requiredModules: ['cpr']));
        $course->changeCompletionSource($source);

        $courses->save($course);

        $urls = new FixedUrls();

        return new AwardCommand(new Awarding(
            new Courses($courses),
            $this->certificates,
            new CredentialIssuer(Kit::issuer(), Kit::signer(), Kit::METHOD),
            new Securing(Kit::signer(), Kit::METHOD, Securing::JWT, $this->clock),
            $urls,
            $this->clock,
            new StatusListLayout('awards', 8),
            completions: $completions,
        ), $urls);
    }
}
