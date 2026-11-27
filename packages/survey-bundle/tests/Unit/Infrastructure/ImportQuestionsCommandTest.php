<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Tests\Unit\Infrastructure;

use Phil\SurveyBundle\Application\Import\QuestionImports;
use Phil\SurveyBundle\Infrastructure\Console\ImportQuestionsCommand;
use Phil\SurveyBundle\Tests\Fixtures\Samples;
use Phil\SurveyBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(ImportQuestionsCommand::class)]
final class ImportQuestionsCommandTest extends TestCase
{
    private Stage $stage;

    private CommandTester $command;

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        $this->stage = new Stage();
        $this->command = new CommandTester(new ImportQuestionsCommand(new QuestionImports($this->stage->questionnaires)));
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            unlink($file);
        }
    }

    public function testTheDryRunShowsEachLineAndSavesNothing(): void
    {
        $this->stage->questionnaires->save(Samples::questionnaire());
        $file = $this->file("question_id,type,label,choices\nregion,text,Where?,\nmood,single_choice,<info>Mood</info>,\nnew_one,yes_no,New?,\n");

        self::assertSame(Command::FAILURE, $this->command->execute(['file' => $file, 'key' => 'games']));
        self::assertSame(
            "Line 2: update\nLine 3: refuse\n  choices: is refused — This type of question lists its choices.\nLine 4: create\n"
            ."1 to create, 1 to replace, 1 refused.\nNothing is saved: run the command again with --write to save the rows accepted.\n",
            $this->command->getDisplay(true),
        );
        self::assertSame('Region', $this->stage->questionnaires->get('games')->question('region')?->label);
    }

    public function testADryRunWithoutRefusalSucceeds(): void
    {
        self::assertSame(Command::SUCCESS, $this->command->execute(['file' => $this->file("question_id,type,label\nq,text,Q\n"), 'key' => 'fresh']));
        self::assertStringEndsWith("1 to create, 0 to replace, 0 refused.\nNothing is saved: run the command again with --write to save the rows accepted.\n", $this->command->getDisplay(true));
        self::assertNull($this->stage->questionnaires->find('fresh'));
    }

    public function testWriteSavesTheRowsAccepted(): void
    {
        $file = $this->file("question_id,type,label\nq,text,Q\nr,email,R\n");

        self::assertSame(Command::SUCCESS, $this->command->execute(['file' => $file, 'key' => 'fresh', '--title' => 'Fresh', '--write' => true]));
        self::assertSame("Line 2: create\nLine 3: create\n2 to create, 0 to replace, 0 refused.\n2 created, 0 replaced, 0 refused.\n", $this->command->getDisplay(true));
        self::assertSame('Fresh', $this->stage->questionnaires->get('fresh')->title);
        self::assertCount(2, $this->stage->questionnaires->get('fresh')->questions());
    }

    public function testWriteNamesTheRowsTheQuestionnaireRefuses(): void
    {
        $this->stage->questionnaires->save(Samples::questionnaire());
        $file = $this->file("question_id,type,label,choices\nfun,text,Fun,\nmood,single_choice,Mood,\nnew_one,yes_no,New?,\n");

        self::assertSame(Command::FAILURE, $this->command->execute(['file' => $file, 'key' => 'games', '--write' => true]));
        $display = $this->command->getDisplay(true);
        self::assertStringContainsString("Line 3: refuse\n  choices: is refused — This type of question lists its choices.\n", $display);
        self::assertStringContainsString("1 to create, 1 to replace, 1 refused.\nLine 2: refused\n  Row: is refused — ", $display);
        self::assertStringEndsWith("1 created, 0 replaced, 2 refused.\n", $display);
        self::assertSame(1, substr_count($display, 'Line 3'));
        self::assertSame('New?', $this->stage->questionnaires->get('games')->question('new_one')?->label);
        self::assertNull($this->stage->questionnaires->get('games')->question('mood'));
        self::assertSame('scale', $this->stage->questionnaires->get('games')->question('fun')?->type->value);
    }

    public function testAFileRefusedByTheTargetAfterAPreviewedOneIsStillNamed(): void
    {
        $this->stage->questionnaires->save(Samples::questionnaire());
        $file = $this->file("question_id,type,label,choices\nmood,single_choice,Mood,\nfun,text,Fun,\n");

        self::assertSame(Command::FAILURE, $this->command->execute(['file' => $file, 'key' => 'games', '--write' => true]));
        self::assertStringContainsString("1 refused.\nLine 3: refused\n  Row: is refused — ", $this->command->getDisplay(true));
    }

    public function testTheErrorsAreWrittenInTheErrorStyle(): void
    {
        $this->command->execute(['file' => '/no/such/file.csv', 'key' => 'games'], ['decorated' => true]);
        self::assertSame("\033[37;41mThe file cannot be read.\033[39;49m\n", $this->command->getDisplay());

        $this->command->execute(['file' => $this->file("question_id,type,label\nq,text,Q\n"), 'key' => 'No key'], ['decorated' => true]);
        self::assertSame("\033[37;41mThe key of a questionnaire is 1 to 64 lower-case letters, digits, \"_\" or \"-\".\033[39;49m\n", $this->command->getDisplay());
    }

    public function testAFileThatCannotBeReadIsInvalid(): void
    {
        self::assertSame(Command::INVALID, $this->command->execute(['file' => '/no/such/file.csv', 'key' => 'games']));
        self::assertSame("The file cannot be read.\n", $this->command->getDisplay(true));
    }

    public function testAFileWithoutTheHeadersItNeedsFails(): void
    {
        self::assertSame(Command::FAILURE, $this->command->execute(['file' => $this->file("question_id,type\nq,text\n"), 'key' => 'games']));
        self::assertStringContainsString('label', $this->command->getDisplay(true));
    }

    public function testAKeyThatIsNoKeyFails(): void
    {
        self::assertSame(Command::FAILURE, $this->command->execute(['file' => $this->file("question_id,type,label\nq,text,Q\n"), 'key' => 'No key']));
        self::assertSame("The key of a questionnaire is 1 to 64 lower-case letters, digits, \"_\" or \"-\".\n", $this->command->getDisplay(true));
    }

    private function file(string $csv): string
    {
        $file = tempnam(sys_get_temp_dir(), 'questions');
        self::assertIsString($file);
        file_put_contents($file, $csv);
        $this->files[] = $file;

        return $file;
    }
}
