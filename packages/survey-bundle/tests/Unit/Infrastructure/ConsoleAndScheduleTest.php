<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Phil\SurveyBundle\Application\Message\PurgeSurveyResponses;
use Phil\SurveyBundle\Application\QuestionnaireJson;
use Phil\SurveyBundle\Domain\ValueObject\Questionnaire;
use Phil\SurveyBundle\Infrastructure\Console\ExportQuestionnaireCommand;
use Phil\SurveyBundle\Infrastructure\Console\ImportQuestionnaireCommand;
use Phil\SurveyBundle\Infrastructure\Scheduler\SurveySchedule;
use Phil\SurveyBundle\Tests\Fixtures\Samples;
use Phil\SurveyBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

#[CoversClass(ExportQuestionnaireCommand::class)]
#[CoversClass(ImportQuestionnaireCommand::class)]
#[CoversClass(SurveySchedule::class)]
final class ConsoleAndScheduleTest extends TestCase
{
    public function testAQuestionnaireIsExportedThenImported(): void
    {
        $stage = new Stage();
        $stage->questionnaires->save(Questionnaire::fromArray(Samples::colour()));

        $export = new CommandTester(new ExportQuestionnaireCommand($stage->questionnaires));

        self::assertSame(Command::SUCCESS, $export->execute(['key' => 'colour']));
        $json = $export->getDisplay(true);
        self::assertStringStartsWith('{', $json);
        self::assertSame(Command::FAILURE, $export->execute(['key' => 'nothing']));
        self::assertStringContainsString('No questionnaire has the key "nothing".', $export->getDisplay());

        $file = tempnam(sys_get_temp_dir(), 'survey');
        self::assertIsString($file);
        file_put_contents($file, $json);
        $import = new CommandTester(new ImportQuestionnaireCommand($stage->questionnaires));

        try {
            self::assertSame(Command::FAILURE, $import->execute(['file' => $file]));
            self::assertStringContainsString('has the key "colour" already', $import->getDisplay());
            self::assertSame(Command::SUCCESS, $import->execute(['file' => $file, '--replace' => true]));
            self::assertSame("The questionnaire \"colour\" is saved.\n", $import->getDisplay(true));
        } finally {
            unlink($file);
        }

        self::assertSame(Command::INVALID, $import->execute(['file' => '/no/such/file.json']));
        self::assertStringContainsString('The file is a readable file of at most 1048576 bytes.', $import->getDisplay());
    }

    public function testTheScheduleRunsThePurgeEveryDayOnOneWorker(): void
    {
        $locks = new LockFactory(new InMemoryStore());
        $provider = new SurveySchedule('02:30', 'America/Toronto', $locks, new ArrayAdapter());
        $schedule = $provider->getSchedule();

        self::assertSame($schedule, $provider->getSchedule());
        self::assertNotNull($schedule->getLock());
        self::assertNotNull($schedule->getState());
        [$recurring] = $schedule->getRecurringMessages();
        $messages = iterator_to_array($recurring->getProvider()->getMessages(new MessageContext('phil_survey', 'id', $recurring->getTrigger(), new DateTimeImmutable())), false);
        self::assertInstanceOf(PurgeSurveyResponses::class, $messages[0]);
        $trigger = $recurring->getTrigger();
        self::assertInstanceOf(TriggerInterface::class, $trigger);
        $next = $trigger->getNextRunDate(new DateTimeImmutable('2026-11-21 12:00:00', new DateTimeZone('America/Toronto')));
        self::assertSame('02:30 America/Toronto', $next?->format('H:i e'));

        $bare = new SurveySchedule()->getSchedule();
        self::assertNull($bare->getLock());
        self::assertNull($bare->getState());
    }

    public function testTheExportIsTheDocumentAloneAndEachRefusalIsAnError(): void
    {
        $stage = new Stage();
        $questionnaire = Questionnaire::fromArray(Samples::colour());
        $stage->questionnaires->save($questionnaire);
        $export = new CommandTester(new ExportQuestionnaireCommand($stage->questionnaires));

        $export->execute(['key' => 'colour']);
        self::assertSame(QuestionnaireJson::encode($questionnaire), $export->getDisplay(true));

        $export->execute(['key' => 'nothing'], ['decorated' => true]);
        self::assertSame("\e[37;41mNo questionnaire has the key \"nothing\".\e[39;49m\n", $export->getDisplay(true));

        $file = tempnam(sys_get_temp_dir(), 'survey');
        self::assertIsString($file);
        file_put_contents($file, '{"format":"phil-survey/1","questionnaire":{}}');
        $import = new CommandTester(new ImportQuestionnaireCommand($stage->questionnaires));

        try {
            self::assertSame(Command::FAILURE, $import->execute(['file' => $file], ['decorated' => true]));
        } finally {
            unlink($file);
        }

        self::assertMatchesRegularExpression('/^\e\[37;41m.+\e\[39;49m\n$/s', $import->getDisplay(true));
        self::assertStringContainsString('The "key" of the questionnaire is a text.', $import->getDisplay());
    }

    public function testAFileOfTheMostBytesIsImportedAndAFolderIsNot(): void
    {
        $stage = new Stage();
        $json = rtrim(QuestionnaireJson::encode(Questionnaire::fromArray(Samples::colour())));
        $file = tempnam(sys_get_temp_dir(), 'survey');
        self::assertIsString($file);
        file_put_contents($file, $json.str_repeat(' ', QuestionnaireJson::MAX_BYTES - \strlen($json)));
        $import = new CommandTester(new ImportQuestionnaireCommand($stage->questionnaires));

        try {
            self::assertSame(QuestionnaireJson::MAX_BYTES, filesize($file));
            self::assertSame(Command::SUCCESS, $import->execute(['file' => $file]));
            file_put_contents($file, ' ', \FILE_APPEND);
            self::assertSame(Command::INVALID, $import->execute(['file' => $file, '--replace' => true]));
        } finally {
            unlink($file);
        }

        self::assertSame(Command::INVALID, $import->execute(['file' => sys_get_temp_dir()]));
    }
}
