<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Console;

use Override;
use Phil\PrivacyBundle\Application\Contract\IncidentRegister;
use Phil\PrivacyBundle\Application\Contract\NoticeOrganisationSource;
use Phil\PrivacyBundle\Application\Incident\IncidentNotices;
use Phil\PrivacyBundle\Domain\Incident\Incident;
use Phil\PrivacyBundle\Domain\Incident\IncidentRevision;
use Phil\PrivacyBundle\Domain\Incident\NoticeOrganisation;
use Phil\PrivacyBundle\Domain\Incident\PublicNoticeReason;
use Phil\PrivacyBundle\Infrastructure\Console\IncidentNoticeCommand;
use Phil\PrivacyBundle\Infrastructure\Twig\TwigIncidentNotice;
use Phil\PrivacyBundle\Tests\Unit\Infrastructure\Console\Fixture\HistoryRegister;
use Phil\PrivacyBundle\Tests\Unit\Infrastructure\Console\Fixture\MemoryRegister;
use Phil\PrivacyBundle\Tests\Unit\Infrastructure\Twig\NoticeFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(IncidentNoticeCommand::class)]
final class IncidentNoticeCommandTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-privacy-notice-'.bin2hex(random_bytes(4));
    }

    #[Override]
    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function testTheDraftGoesToTheStandardOutputAndTheRestToTheErrorOutput(): void
    {
        $register = $this->register(NoticeFixtures::complete());
        $tester = $this->tester($register);

        self::assertSame(Command::SUCCESS, $tester->execute(['incident' => NoticeFixtures::ID, '--to' => 'commission', '--locale' => 'en'], ['capture_stderr_separately' => true]));
        self::assertSame(file_get_contents(NoticeFixtures::SNAPSHOTS.'/commission-complete.en.md'), $tester->getDisplay());
        self::assertSame(
            "To complete in the draft: s. 3, para. 4 (cause); s. 3, para. 7 (affected in quebec); s. 3, para. 11 (other authority).\n"
            .'Nothing was sent. Review the draft, send it to the Commission in writing, then record the date: phil:privacy:incident --id='.NoticeFixtures::ID." --authority-notified=<date>.\n",
            $tester->getErrorOutput(),
        );
        self::assertSame(0, $register->saves, 'The command records nothing.');
    }

    public function testTheDraftIsInFrenchByDefault(): void
    {
        $tester = $this->tester($this->register(NoticeFixtures::complete()));

        $tester->execute(['incident' => NoticeFixtures::ID, '--to' => 'subject'], ['capture_stderr_separately' => true]);

        self::assertSame(file_get_contents(NoticeFixtures::SNAPSHOTS.'/subject-complete.fr.md'), $tester->getDisplay());
        self::assertStringContainsString('To complete in the draft: s. 5, para. 5 (suggested measures).', $tester->getErrorOutput());
        self::assertStringContainsString('phil:privacy:incident --id='.NoticeFixtures::ID.' --subjects-notified=<date>.', $tester->getErrorOutput());
        self::assertStringContainsString('The notice goes to each person; a public notice replaces it only when sending it would cause greater injury, would be excessively difficult, or the organisation has no contact details for the people concerned (section 6).', $tester->getErrorOutput());
    }

    public function testWithoutAnErrorOutputTheMessagesFollowTheDraft(): void
    {
        $output = new BufferedOutput();
        $command = $this->command($this->register(NoticeFixtures::incomplete()), false);

        self::assertSame(Command::SUCCESS, $command->run(new ArrayInput(['incident' => NoticeFixtures::ID, '--to' => 'commission', '--locale' => 'fr']), $output));

        $display = $output->fetch();
        $draft = (string) file_get_contents(NoticeFixtures::SNAPSHOTS.'/commission-incomplete.fr.md');
        self::assertSame($draft, substr($display, 0, \strlen($draft)));
        self::assertStringContainsString('To complete in the draft: s. 3, para. 1 (organisation); s. 3, para. 1 (enterprise number); s. 3, para. 2 (contact); s. 3, para. 3 (information); s. 3, para. 4 (cause); s. 3, para. 5 (occurrence); s. 3, para. 7 (affected);', $display);
    }

    public function testAPublicNoticeIsRefusedWithoutAReasonAndRemindsTheNoticeToEachPerson(): void
    {
        $refused = $this->tester($this->register(NoticeFixtures::complete()));
        self::assertSame(Command::FAILURE, $refused->execute(['incident' => NoticeFixtures::ID, '--to' => 'public'], ['capture_stderr_separately' => true]));
        self::assertSame('', $refused->getDisplay());
        self::assertStringContainsString('excessively difficult (excessive_hardship)', $refused->getErrorOutput());

        $replacing = $this->tester($this->register(NoticeFixtures::complete(PublicNoticeReason::NoContactDetails)));
        self::assertSame(Command::SUCCESS, $replacing->execute(['incident' => NoticeFixtures::ID, '--to' => 'public', '--locale' => 'en'], ['capture_stderr_separately' => true]));
        self::assertSame(file_get_contents(NoticeFixtures::SNAPSHOTS.'/public-complete.en.md'), $replacing->getDisplay());
        self::assertStringContainsString('then record it: phil:privacy:incident --id='.NoticeFixtures::ID.' --public-notice=<date> --public-notice-reason=no_contact_details.', $replacing->getErrorOutput());
        self::assertStringContainsString('The register records a public notice reason (no_contact_details) that replaces the notice to each person (section 6).', $replacing->getErrorOutput());

        $quick = $this->tester($this->register(NoticeFixtures::incomplete(PublicNoticeReason::PromptAction)));
        self::assertSame(Command::SUCCESS, $quick->execute(['incident' => NoticeFixtures::ID, '--to' => 'public'], ['capture_stderr_separately' => true]));
        self::assertStringContainsString('A public notice given to act quickly does not replace the notice to each person, which is still owed promptly (section 6).', $quick->getErrorOutput());
    }

    public function testAFollowUpListsWhatChangedSinceTheNotice(): void
    {
        $tester = $this->tester($this->register(NoticeFixtures::complete(), NoticeFixtures::revisions()));

        self::assertSame(Command::SUCCESS, $tester->execute(['incident' => NoticeFixtures::ID, '--to' => 'commission', '--follow-up' => true, '--locale' => 'fr'], ['capture_stderr_separately' => true]));
        self::assertSame(file_get_contents(NoticeFixtures::SNAPSHOTS.'/follow_up-complete.fr.md'), $tester->getDisplay());
        self::assertStringContainsString('To complete in the draft: s. 3, para. 4 (cause); s. 3, para. 7 (affected in quebec).', $tester->getErrorOutput());
        self::assertStringContainsString('Nothing was sent. Review the follow-up, send it to the Commission promptly (section 4), and keep its date: give it to --since for the next follow-up.', $tester->getErrorOutput());

        $later = $this->tester($this->register(NoticeFixtures::complete(), NoticeFixtures::revisions()));
        self::assertSame(Command::SUCCESS, $later->execute(['incident' => NoticeFixtures::ID, '--to' => 'commission', '--follow-up' => true, '--since' => '2026-11-14'], ['capture_stderr_separately' => true]));
        self::assertSame('', $later->getDisplay());
        self::assertSame("No rubric of section 3 changed since 2026-11-14 00:00: there is no follow-up to draft.\n", $later->getErrorOutput());
    }

    public function testAFollowUpNeedsTheCorrectionsAndANotice(): void
    {
        $withoutHistory = $this->tester(new MemoryRegister(NoticeFixtures::complete()));
        self::assertSame(Command::FAILURE, $withoutHistory->execute(['incident' => NoticeFixtures::ID, '--to' => 'commission', '--follow-up' => true], ['capture_stderr_separately' => true]));
        self::assertStringContainsString('does not keep its corrections (IncidentHistory)', $withoutHistory->getErrorOutput());

        $withoutNotice = $this->tester($this->register(NoticeFixtures::incomplete()));
        self::assertSame(Command::FAILURE, $withoutNotice->execute(['incident' => NoticeFixtures::ID, '--to' => 'commission', '--follow-up' => true], ['capture_stderr_separately' => true]));
        self::assertStringContainsString('the register records none for this incident', $withoutNotice->getErrorOutput());

        $badDate = $this->tester($this->register(NoticeFixtures::complete()));
        self::assertSame(Command::FAILURE, $badDate->execute(['incident' => NoticeFixtures::ID, '--to' => 'commission', '--follow-up' => true, '--since' => 'not a date'], ['capture_stderr_separately' => true]));
        self::assertSame("--since is a date, such as 2026-11-10 or \"2026-11-10 14:30\".\n", $badDate->getErrorOutput());
    }

    public function testBadOptionsAndAnUnknownIncidentAreRefused(): void
    {
        foreach ([
            '--to is one of commission, subject, public.' => ['--to' => 'cai'],
            '--locale is one of fr, en.' => ['--to' => 'commission', '--locale' => 'de'],
            'A follow-up (section 4) is sent to the Commission only: --follow-up goes with --to=commission.' => ['--to' => 'subject', '--follow-up' => true],
            '--since goes with --follow-up.' => ['--to' => 'commission', '--since' => '2026-11-14'],
        ] as $message => $options) {
            $tester = $this->tester($this->register(NoticeFixtures::complete()));
            self::assertSame(Command::INVALID, $tester->execute(['incident' => NoticeFixtures::ID, ...$options], ['capture_stderr_separately' => true]), $message);
            self::assertSame($message."\n", $tester->getErrorOutput());
        }

        $missingTo = $this->tester($this->register(NoticeFixtures::complete()));
        self::assertSame(Command::INVALID, $missingTo->execute(['incident' => NoticeFixtures::ID], ['capture_stderr_separately' => true]));

        $unknown = $this->tester($this->register(NoticeFixtures::complete()));
        self::assertSame(Command::FAILURE, $unknown->execute(['incident' => 'ffffffffffffffffffffffffffffffff', '--to' => 'commission'], ['capture_stderr_separately' => true]));
        self::assertSame("No incident has that identifier.\n", $unknown->getErrorOutput());
    }

    public function testEmptyOptionsAreLeftOut(): void
    {
        $tester = $this->tester(self::register(NoticeFixtures::complete()));

        self::assertSame(Command::SUCCESS, $tester->execute(['incident' => NoticeFixtures::ID, '--to' => 'commission', '--locale' => 'en', '--output' => '', '--since' => ''], ['capture_stderr_separately' => true]));
        self::assertSame(file_get_contents(NoticeFixtures::SNAPSHOTS.'/commission-complete.en.md'), $tester->getDisplay());
    }

    public function testTheOptionsNameTheirValues(): void
    {
        $definition = $this->command(self::register(NoticeFixtures::complete()))->getDefinition();

        self::assertSame('Who the notice is for: commission, subject, public', $definition->getOption('to')->getDescription());
        self::assertSame('The language of the draft: fr, en', $definition->getOption('locale')->getDescription());
        self::assertSame('fr', $definition->getOption('locale')->getDefault());
    }

    public function testWithoutARiskOfSeriousInjuryTheCommandSaysTheNoticesAreNotOwed(): void
    {
        $tester = $this->tester($this->register(NoticeFixtures::complete()->update(seriousRisk: false)));

        self::assertSame(Command::SUCCESS, $tester->execute(['incident' => NoticeFixtures::ID, '--to' => 'commission'], ['capture_stderr_separately' => true]));
        self::assertStringContainsString('The register does not record a risk of serious injury: the notices are owed for one only (section 3.5 of the Act respecting the protection of personal information in the private sector).', $tester->getErrorOutput());
    }

    public function testTheDraftCanBeWrittenToANewFileOnlyItsOwnerReads(): void
    {
        $file = $this->directory.'/notice.md';
        $tester = $this->tester($this->register(NoticeFixtures::complete()));

        self::assertSame(Command::SUCCESS, $tester->execute(['incident' => NoticeFixtures::ID, '--to' => 'commission', '--locale' => 'en', '--output' => $file]));
        self::assertFileEquals(NoticeFixtures::SNAPSHOTS.'/commission-complete.en.md', $file);
        self::assertSame(0o600, fileperms($file) & 0o777);
        self::assertStringStartsWith('Draft written to '.$file.".\nTo complete in the draft:", $tester->getDisplay());

        $again = $this->tester($this->register(NoticeFixtures::complete()));
        self::assertSame(Command::FAILURE, $again->execute(['incident' => NoticeFixtures::ID, '--to' => 'subject', '--output' => $file]));
        self::assertSame($file." already exists: give a new file to --output.\n", $again->getDisplay());
        self::assertFileEquals(NoticeFixtures::SNAPSHOTS.'/commission-complete.en.md', $file);
    }

    public function testADraftThatCannotBeWrittenIsSaid(): void
    {
        new Filesystem()->dumpFile($this->directory.'/file', '');
        $tester = $this->tester($this->register(NoticeFixtures::complete()));

        self::assertSame(Command::FAILURE, $tester->execute(['incident' => NoticeFixtures::ID, '--to' => 'commission', '--output' => $this->directory.'/file/notice.md']));
        self::assertSame('The draft could not be written to '.$this->directory."/file/notice.md.\n", $tester->getDisplay());
    }

    private function tester(IncidentRegister $register): CommandTester
    {
        return new CommandTester($this->command($register));
    }

    private function command(IncidentRegister $register, bool $organisation = true): IncidentNoticeCommand
    {
        $source = new class implements NoticeOrganisationSource {
            public function organisation(): NoticeOrganisation
            {
                return NoticeFixtures::organisation();
            }
        };

        return new IncidentNoticeCommand($register, new IncidentNotices($organisation ? $source : null), new TwigIncidentNotice(NoticeFixtures::twig()), new MockClock('2026-11-21 12:00', 'America/Toronto'));
    }

    /**
     * @param list<IncidentRevision> $revisions
     */
    private function register(Incident $incident, array $revisions = []): HistoryRegister
    {
        return new HistoryRegister($incident, $revisions);
    }
}
