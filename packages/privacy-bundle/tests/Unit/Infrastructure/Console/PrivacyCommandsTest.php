<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Console;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Phil\PrivacyBundle\Application\Register\ProcessingRegister;
use Phil\PrivacyBundle\Application\SubjectErasure;
use Phil\PrivacyBundle\Application\SubjectExport;
use Phil\PrivacyBundle\Domain\Retention\AccessResponse;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Retention\Mechanism;
use Phil\PrivacyBundle\Domain\Retention\Processing;
use Phil\PrivacyBundle\Domain\Retention\Processor;
use Phil\PrivacyBundle\Domain\Retention\RetentionPeriod;
use Phil\PrivacyBundle\Domain\Retention\RetentionRule;
use Phil\PrivacyBundle\Domain\Retention\RetentionSchedule;
use Phil\PrivacyBundle\Domain\Subject\DataCategory;
use Phil\PrivacyBundle\Infrastructure\Console\EraseSubjectCommand;
use Phil\PrivacyBundle\Infrastructure\Console\ExportSubjectCommand;
use Phil\PrivacyBundle\Infrastructure\Console\ProcessingRegisterCommand;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\FixedExporter;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\FixedScheduleSource;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\RecordingEraser;
use Phil\PrivacyBundle\Tests\Unit\Domain\Declaration\Fixture\BaseModel;
use Phil\PrivacyBundle\Tests\Unit\Domain\Declaration\Fixture\Member;
use Phil\PrivacyBundle\Tests\Unit\Infrastructure\Console\Fixture\Entity\Subscriber;
use Phil\PrivacyBundle\Tests\Unit\Infrastructure\Console\Fixture\FaultyFiles;
use Phil\PrivacyBundle\Tests\Unit\Infrastructure\Console\Fixture\OneManagerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(ExportSubjectCommand::class)]
#[CoversClass(EraseSubjectCommand::class)]
#[CoversClass(ProcessingRegisterCommand::class)]
final class PrivacyCommandsTest extends TestCase
{
    public function testAnExportIsPrintedOrWritten(): void
    {
        $command = new ExportSubjectCommand(new SubjectExport([new FixedExporter('newsletter', ['email' => 'ada@example.com']), new FixedExporter('identity', ['name' => 'Ada Lovelace / Byron'])]));
        self::assertSame('phil:privacy:export', $command->getName());

        $printed = new CommandTester($command);
        self::assertSame(Command::SUCCESS, $printed->execute(['subject' => 's-1']));
        self::assertSame(<<<'JSON'
            {
                "identity": {
                    "subject": "s-1",
                    "name": "Ada Lovelace / Byron"
                },
                "newsletter": {
                    "subject": "s-1",
                    "email": "ada@example.com"
                }
            }

            JSON, $printed->getDisplay());

        $file = sys_get_temp_dir().'/phil-privacy-'.bin2hex(random_bytes(6)).'.json';
        $umask = umask(0o022);
        $written = new CommandTester($command);

        try {
            self::assertSame(Command::SUCCESS, $written->execute(['subject' => 'é-1', '--output' => $file]));
            $after = umask();
        } finally {
            umask($umask);
        }

        self::assertSame("The answer for é-1 is in {$file}.\n", $written->getDisplay());
        self::assertStringContainsString('"subject": "é-1"', (string) file_get_contents($file));
        self::assertSame(0o600, fileperms($file) & 0o777, 'An access answer is readable by its owner only, whatever the umask.');
        self::assertSame(0o022, $after, 'The umask is given back.');
        unlink($file);

        $refused = new CommandTester($command);
        self::assertSame(Command::INVALID, $refused->execute(['subject' => 'a b']));
        self::assertSame("A data subject identifier cannot contain whitespace or control characters.\n", $refused->getDisplay());
    }

    public function testAnExportFileIsNeverReplacedSilentlyNorReportedWhenNotWritten(): void
    {
        $command = new ExportSubjectCommand(new SubjectExport([new FixedExporter('newsletter', ['email' => 'ada@example.com'])]));
        $file = sys_get_temp_dir().'/phil-privacy-'.bin2hex(random_bytes(6)).'.json';
        file_put_contents($file, 'an earlier answer');
        chmod($file, 0o644);

        $kept = new CommandTester($command);
        self::assertSame(Command::FAILURE, $kept->execute(['subject' => 's-1', '--output' => $file]));
        self::assertSame("The answer for s-1 was not written: {$file} exists; pass --force to replace it.\n", $kept->getDisplay());
        self::assertSame('an earlier answer', file_get_contents($file));

        file_put_contents($file, str_repeat('an earlier, longer answer ', 20));
        $replaced = new CommandTester($command);
        self::assertSame(Command::SUCCESS, $replaced->execute(['subject' => 's-1', '--output' => $file, '--force' => true]));
        self::assertSame("{\n    \"newsletter\": {\n        \"subject\": \"s-1\",\n        \"email\": \"ada@example.com\"\n    }\n}\n", file_get_contents($file), 'Nothing of the longer file is left after the answer.');
        self::assertSame(0o600, fileperms($file) & 0o777, 'A replaced file is narrowed to its owner too.');
        unlink($file);

        $nowhere = sys_get_temp_dir().'/phil-privacy-'.bin2hex(random_bytes(6)).'/answer.json';
        $failed = new CommandTester($command);
        self::assertSame(Command::FAILURE, $failed->execute(['subject' => 's-1', '--output' => $nowhere]));
        self::assertSame("The answer for s-1 was not written: {$nowhere} cannot be opened for writing.\n", $failed->getDisplay());
        self::assertFileDoesNotExist($nowhere);
    }

    public function testAnExportFileIsCreatedForItsOwnerAloneAndAFailedStepIsAFailure(): void
    {
        $command = new ExportSubjectCommand(new SubjectExport([new FixedExporter('newsletter', ['email' => 'ada@example.com'])]));
        $file = FaultyFiles::SCHEME.'://answer.json';
        $umask = umask(0o022);

        try {
            FaultyFiles::register();
            self::assertSame(Command::SUCCESS, new CommandTester($command)->execute(['subject' => 's-1', '--output' => $file]));
            self::assertSame(0o077, FaultyFiles::$umask, 'The file is created under a umask that keeps it to its owner.');
            self::assertStringEndsWith("}\n", FaultyFiles::$files[$file] ?? '');

            foreach (['chmod', 'write', 'truncate', 'flush'] as $failing) {
                FaultyFiles::register($failing);
                $tester = new CommandTester($command);
                self::assertSame(Command::FAILURE, $tester->execute(['subject' => 's-1', '--output' => $file]), $failing);
                self::assertSame("The answer for s-1 was not written: {$file} could not be written in full.\n", $tester->getDisplay(), $failing);
                self::assertSame([], FaultyFiles::$files, 'A file created and not filled is removed.');
            }

            FaultyFiles::register('open');

            try {
                new CommandTester($command)->execute(['subject' => 's-1', '--output' => $file]);
                self::fail('An opening that throws was swallowed.');
            } catch (RuntimeException $runtimeException) {
                self::assertSame('The disk is gone.', $runtimeException->getMessage());
            }

            self::assertSame(0o022, umask(), 'The umask is given back when the opening throws.');
        } finally {
            umask($umask);
            FaultyFiles::unregister();
        }
    }

    public function testAnErasureSaysWhatEachStoreDid(): void
    {
        $command = new EraseSubjectCommand(new SubjectErasure([new RecordingEraser('newsletter'), new RecordingEraser('identity')]));
        self::assertSame('phil:privacy:erase', $command->getName());

        $erased = new CommandTester($command);
        self::assertSame(Command::SUCCESS, $erased->execute(['subject' => 's-1', '--confirm' => true]));
        self::assertSame("identity: records, deleted\nnewsletter: records, deleted\n", $erased->getDisplay());

        $unconfirmed = new CommandTester($command);
        self::assertSame(Command::INVALID, $unconfirmed->execute(['subject' => 's-1']));
        self::assertSame("Nothing was erased: add --confirm to erase s-1 from every module.\n", $unconfirmed->getDisplay());

        $refused = new CommandTester($command);
        self::assertSame(Command::INVALID, $refused->execute(['subject' => '', '--confirm' => true]));
        self::assertSame("A data subject identifier cannot be empty.\n", $refused->getDisplay());
    }

    public function testAnIncompleteErasureNamesTheModulesThatFailed(): void
    {
        $tester = new CommandTester(new EraseSubjectCommand(new SubjectErasure([new RecordingEraser('billing', fails: true), new RecordingEraser('identity')])));

        self::assertSame(Command::FAILURE, $tester->execute(['subject' => 's-1', '--confirm' => true]));
        self::assertSame("identity: records, deleted\nbilling: failed, RuntimeException\nErasure failed in module(s) billing; retry it, erasers are idempotent.\n", $tester->getDisplay());
    }

    /**
     * The message of a failure can quote the person's data: it is shown only to whoever asks for it.
     */
    public function testTheMessageOfAFailureIsShownOnlyWhenVerbose(): void
    {
        $tester = new CommandTester(new EraseSubjectCommand(new SubjectErasure([new RecordingEraser('billing', fails: true)])));

        self::assertSame(Command::FAILURE, $tester->execute(['subject' => 's-1', '--confirm' => true], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]));
        self::assertSame("billing: failed, RuntimeException\nbilling: billing is down\nErasure failed in module(s) billing; retry it, erasers are idempotent.\n", $tester->getDisplay());
    }

    public function testTheRegisterIsATableOrJson(): void
    {
        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([__DIR__.'/Fixture/Entity']));
        $config->enableNativeLazyObjects(true);

        $registry = new OneManagerRegistry(new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config));
        $command = new ProcessingRegisterCommand(new ProcessingRegister(), [Member::class, BaseModel::class], $registry);
        self::assertSame('phil:privacy:register', $command->getName());

        $table = new CommandTester($command);
        self::assertSame(Command::SUCCESS, $table->execute([]));
        self::assertSame(implode("\n", [
            '## Processing activities',
            '',
            '| Rule | Store | Categories | Purpose | Recipients | Processors | Location | Period | Fields |',
            '|---|---|---|---|---|---|---|---|---|',
            '',
            '## Personal fields',
            '',
            '| Category | Class | Property | Retention | Store | Period | Mechanism | Erasure | Personal data left |',
            '|---|---|---|---|---|---|---|---|---|',
            '| email | '.Member::class.' | email | R-002 | — | — | — | — | — |',
            '| email | '.Subscriber::class.' | email | R-002 | — | — | — | — | — |',
            '| ip | '.BaseModel::class.' | createdFrom | R-009 | — | — | — | — | — |',
            '| person_name | '.Member::class.' | displayName | — | — | — | — | — | — |',
        ])."\n", $table->getDisplay());

        $json = new CommandTester(new ProcessingRegisterCommand(new ProcessingRegister(), [Member::class]));
        self::assertSame(Command::SUCCESS, $json->execute(['--format' => 'json']));
        $decoded = json_decode($json->getDisplay(), true);
        self::assertIsArray($decoded);
        self::assertSame(['activities', 'fields', 'gaps'], array_keys($decoded));
        self::assertIsArray($decoded['fields']);
        self::assertCount(3, $decoded['fields']);
        self::assertSame(['category' => 'email', 'class' => Member::class, 'property' => 'email', 'retention' => 'R-002', 'store' => null, 'period' => null, 'mechanism' => null, 'erasure' => null, 'personal_data_left' => null], $decoded['fields'][0]);
        self::assertStringStartsWith("{\n    \"activities\": [],\n    \"fields\": [\n        {\n            \"category\": \"email\",\n            \"class\": \"Phil\\\\PrivacyBundle", $json->getDisplay());

        $refused = new CommandTester($command);
        self::assertSame(Command::INVALID, $refused->execute(['--format' => 'csv']));
        self::assertSame("The format is markdown or json.\n", $refused->getDisplay());
    }

    public function testTheActivitiesComeFromTheScheduleAndStrictFailsOnAGap(): void
    {
        $schedule = new RetentionSchedule([
            new RetentionRule('R-002', 'members', [DataCategory::fromString('email')], RetentionPeriod::fixed('P24M'), Mechanism::Job, ErasureEffect::Deleted, AccessResponse::Returned, new Processing(
                "Membership | renewals\nand news",
                ['Team A', 'Team B'],
                [new Processor('Host', 'Canada')],
                'Canada',
            )),
            new RetentionRule('R-009', 'logs', [DataCategory::fromString('ip')], RetentionPeriod::fixed('P30D'), Mechanism::Job, ErasureEffect::Deleted, AccessResponse::Returned),
        ]);
        $command = new ProcessingRegisterCommand(new ProcessingRegister(new FixedScheduleSource($schedule)), [Member::class, BaseModel::class]);

        $tester = new CommandTester($command);
        self::assertSame(Command::SUCCESS, $tester->execute([]));
        $display = $tester->getDisplay();
        self::assertStringContainsString('| R-002 | members | email | Membership \\| renewals and news | Team A, Team B | Host (Canada) | Canada | P24M | '.Member::class.'::$email |', $display);
        self::assertStringContainsString('| R-009 | logs | ip | — | — | — | — | P30D | '.BaseModel::class.'::$createdFrom |', $display);
        self::assertStringEndsWith("## Gaps\n\n- Rule R-009 (logs) holds personal information and states no purpose.\n- Rule R-009 (logs) holds personal information and states no location.\n", $display);

        $strict = new CommandTester($command);
        self::assertSame(Command::FAILURE, $strict->execute(['--strict' => true], ['capture_stderr_separately' => true]));
        self::assertSame("The register has 2 gap(s).\n", $strict->getErrorOutput());

        $json = new CommandTester($command);
        self::assertSame(Command::FAILURE, $json->execute(['--format' => 'json', '--strict' => true], ['capture_stderr_separately' => true]));
        $decoded = json_decode($json->getDisplay(), true);
        self::assertIsArray($decoded);
        self::assertIsArray($decoded['activities']);
        self::assertCount(2, $decoded['activities']);
        self::assertIsArray($decoded['gaps']);
        self::assertCount(2, $decoded['gaps']);
    }

    public function testTheRegisterSaysWhetherPersonalDataIsLeft(): void
    {
        $schedule = new RetentionSchedule([
            new RetentionRule('R-002', 'members', [DataCategory::fromString('email')], RetentionPeriod::fixed('P24M'), Mechanism::Job, ErasureEffect::Deleted, AccessResponse::Returned),
            new RetentionRule('R-009', 'logs', [DataCategory::fromString('ip')], RetentionPeriod::fixed('P30D'), Mechanism::Job, ErasureEffect::Pseudonymised, AccessResponse::Returned),
        ]);
        $tester = new CommandTester(new ProcessingRegisterCommand(new ProcessingRegister(new FixedScheduleSource($schedule)), [Member::class, BaseModel::class]));

        $tester->execute([]);

        self::assertStringContainsString('| email | '.Member::class.' | email | R-002 | members | P24M | job | deleted | no |', $tester->getDisplay());
        self::assertStringContainsString('| ip | '.BaseModel::class.' | createdFrom | R-009 | logs | P30D | job | pseudonymised | yes |', $tester->getDisplay());
    }
}
